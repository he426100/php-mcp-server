<?php

declare(strict_types=1);

namespace He426100\McpServer\Service;

use Mcp\Annotation\Tool;
use Psr\Log\LoggerInterface;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\NoResponseAvailable;
use HeadlessChromium\Exception\OperationTimedOut;
use HeadlessChromium\Page;

class TobaccoSearchService extends BaseService
{
    // Define connection details (consider making these configurable)
    private const CHROME_HOST = '127.0.0.1';
    private const CHROME_PORT = 9222; // Default remote debugging port
    private const BROWSER_TIMEOUT = 30000; // 30 seconds in milliseconds
    private const MAX_RESULTS_TO_OPEN = 5;
    private const TARGET_PRODUCT_URL_PREFIX = 'http://www.etmoc.com/firms/Product';
    private const GOOGLE_SEARCH_URL_TEMPLATE = 'https://www.google.com/search?q=site:etmoc.com+%s&hl=en'; // Use hl=en for potentially more consistent selectors

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct($logger);
    }

    #[Tool(
        name: 'search_tobacco_products',
        description: '在烟草市场网(etmoc.com)通过Google搜索烟草产品并提取信息.',
        parameters: [
            'keywords' => [
                'description' => '搜索关键词 (可以是产品名称、条码等)',
                'required' => true,
                'type' => 'string' // Explicitly define type for clarity
            ]
        ]
    )]
    public function searchTobaccoProducts(string $keywords): array
    {
        $this->logger->info('Starting tobacco search for keywords: {keywords}', ['keywords' => $keywords]);
        $results = [];
        $browser = null;
        $page = null; // Main page for search results

        try {
            // Connect to Chrome (ensure Chrome is running with --remote-debugging-port=9222)
            // Alternatively, use BrowserFactory('/path/to/chrome/or/chromium') to launch
            $browserFactory = new BrowserFactory();
            $browser = $browserFactory->createBrowser([
                'remoteDebuggingPort' => self::CHROME_PORT,
                'windowSize' => [1280, 800], // Optional: Set window size
                'enableImages' => false, // Optional: Disable images for speed
                'keepAlive' => true, // Keep alive after script finishes? Set to false if you want it to close
                'noSandbox' => true, // Often needed when running as root or in containers
                'headless' => true,
                'proxyServer' => '127.0.0.1:7897',
                'userDataDir' => BASE_PATH . '/runtime/chrome-user-data',
                'customFlags' => ['--lang=zh-CN', '--enable-automation=false', '--disable-blink-features=AutomationControlled'],
            ]);

            $page = $browser->createPage();

            // 1. Perform Google Search
            $searchUrl = sprintf(self::GOOGLE_SEARCH_URL_TEMPLATE, urlencode($keywords));
            $this->logger->info('Navigating to Google search: {url}', ['url' => $searchUrl]);
            $page->navigate($searchUrl)->waitForNavigation();

            // 2. Extract top etmoc.com product links
            // Google selectors can change! This is an example, inspect Google results page if it breaks.
            // Common selectors: div.g a, div.yuRUbf a, h3.LC20lb
            $this->logger->info('Extracting search result links from Google');
            $jsGetLinks = <<<JS
                (() => {
                    const links = [];
                    // Try a few common selectors for Google results
                    const selectors = ['div.yuRUbf a', 'div.g div.rc a', 'h3.LC20lb a'];
                    let linkElements = [];
                    for (const selector of selectors) {
                         linkElements = Array.from(document.querySelectorAll(selector));
                         if (linkElements.length > 0) break; // Stop if found
                    }

                    linkElements.forEach(a => {
                         // Ensure it's an absolute URL and points to etmoc.com
                        if (a.href && a.href.startsWith('http') && a.href.includes('etmoc.com')) {
                             // Sometimes Google uses redirect URLs, try to get the real one from ping attribute or text
                            let cleanUrl = a.href;
                            if (a.ping) {
                                try {
                                    const urlParams = new URLSearchParams(new URL(a.ping).search);
                                    if (urlParams.has('url')) {
                                        cleanUrl = urlParams.get('url');
                                    }
                                } catch (e) { /* ignore parsing errors */ }
                            }
                            // Further clean up if needed (remove google tracking params)
                            if (cleanUrl.includes('etmoc.com')) { // Double check
                                links.push(cleanUrl);
                            }
                        }
                    });
                    return links;
                })();
            JS;

            $googleLinks = $page->evaluate($jsGetLinks)->getReturnValue();
            $this->logger->debug('Raw links found on Google: {links}', ['links' => $googleLinks]);

            $productLinks = [];
            foreach ($googleLinks as $link) {
                if (str_starts_with($link, self::TARGET_PRODUCT_URL_PREFIX)) {
                    $productLinks[] = $link;
                    if (count($productLinks) >= self::MAX_RESULTS_TO_OPEN) {
                        break;
                    }
                }
            }

            if (empty($productLinks)) {
                $this->logger->warning('No relevant product links found on Google search results for keywords: {keywords}', ['keywords' => $keywords]);
                return []; // Return early if no suitable links found
            }

            $this->logger->info('Found {count} potential product links to process.', ['count' => count($productLinks)]);

            // 3. Process each product link
            $isKeywordBarcode = $this->isBarcode($keywords);
            $this->logger->info('Keyword "{keywords}" interpreted as {type}', [
                'keywords' => $keywords,
                'type' => $isKeywordBarcode ? 'Barcode' : 'Text'
            ]);

            foreach ($productLinks as $index => $productUrl) {
                $this->logger->info('Processing link #{index}: {url}', ['index' => $index + 1, 'url' => $productUrl]);
                $productPage = null; // Use a new page/tab for each product for isolation

                try {
                    $productPage = $browser->createPage();
                    $productPage->navigate($productUrl)->waitForNavigation(Page::LOAD, self::BROWSER_TIMEOUT);
                    $this->logger->debug('Navigated to product page: {url}', ['url' => $productUrl]);

                    // Wait for essential elements to be present
                    $productPage->waitUntilContainsElement('.col98 .brand-title', self::BROWSER_TIMEOUT / 2);
                    $productPage->waitUntilContainsElement('.col98 .row .col-8', self::BROWSER_TIMEOUT / 2);
                    $productPage->waitUntilContainsElement('.col98 .row .col-4 img:first-child', self::BROWSER_TIMEOUT / 2);

                    // 4. Scrape data
                    $title = $this->getText($productPage, '.col98 .brand-title');
                    $imageUrl = $this->getAttribute($productPage, '.col98 .row .col-4 img:first-child', 'src');
                    $infoText = $this->getText($productPage, '.col98 .row .col-8');

                    if ($title === null || $imageUrl === null || $infoText === null) {
                        $this->logger->warning('Failed to scrape essential data from {url}', ['url' => $productUrl]);
                        continue; // Skip if core data missing
                    }
                    $this->logger->debug('Scraped data: Title="{title}", Image="{img}", Info Length={len}', ['title' => $title, 'img' => $imageUrl, 'len' => strlen($infoText)]);


                    // 5. Filter results
                    $keepResult = false;
                    if ($isKeywordBarcode) {
                        if (str_contains($infoText, $keywords)) {
                            $keepResult = true;
                            $this->logger->info('Barcode Match: Found barcode "{barcode}" in info text.', ['barcode' => $keywords]);
                        } else {
                            $this->logger->info('Discarding (Barcode Filter): Barcode "{barcode}" not found in info text.', ['barcode' => $keywords]);
                        }
                    } else {
                        // Case-insensitive comparison for non-barcode keywords in title
                        if (stripos($title, $keywords) !== false) {
                            $keepResult = true;
                            $this->logger->info('Keyword Match: Found keyword "{keyword}" in title "{title}".', ['keyword' => $keywords, 'title' => $title]);
                        } else {
                            $this->logger->info('Discarding (Keyword Filter): Keyword "{keyword}" not found in title "{title}".', ['keyword' => $keywords, 'title' => $title]);
                        }
                    }

                    // 6. Add valid result
                    if ($keepResult) {
                        $results[] = [
                            'title' => trim($title),
                            'imageUrl' => trim($imageUrl),
                            'info' => trim($infoText),
                            'url' => $productUrl,
                        ];
                        $this->logger->info('Added valid product result from {url}', ['url' => $productUrl]);
                    }
                } catch (OperationTimedOut $e) {
                    $this->logger->error('Timeout processing product link: {url} - {error}', ['url' => $productUrl, 'error' => $e->getMessage()]);
                } catch (NoResponseAvailable $e) {
                    $this->logger->error('No response/Navigation error for product link: {url} - {error}', ['url' => $productUrl, 'error' => $e->getMessage()]);
                } catch (\Throwable $e) {
                    // Catch broader errors during scraping/filtering for a specific page
                    $this->logger->error('Error processing product link {url}: {error} - Trace: {trace}', [
                        'url' => $productUrl,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString() // Log trace for debugging
                    ]);
                } finally {
                    // Close the product page tab regardless of success/failure
                    if ($productPage) {
                        try {
                            $this->logger->debug('Closing product page tab for {url}', ['url' => $productUrl]);
                            $productPage->close();
                        } catch (\Exception $closeErr) {
                            $this->logger->error('Error closing product page tab: {error}', ['error' => $closeErr->getMessage()]);
                        }
                    }
                } // End try-catch-finally for single product link
            } // End foreach product link loop

        } catch (\HeadlessChromium\Exception\BrowserConnectionFailed $e) {
            $this->logger->critical('Failed to connect to Chrome Browser. Is it running with --remote-debugging-port={port}? Error: {error}', ['port' => self::CHROME_PORT, 'error' => $e->getMessage()]);
            // Re-throw or return error structure if needed by MCP host
            // throw $e; // Or return an error message array
            return ['error' => 'Failed to connect to browser instance. Please ensure it is running correctly.'];
        } catch (\Throwable $e) {
            // Catch general errors (browser creation, google search navigation, etc.)
            $this->logger->error('An unexpected error occurred during tobacco search: {error} - Trace: {trace}', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() // More detailed trace
            ]);
            return ['error' => 'An unexpected error occurred: ' . $e->getMessage()];
        } finally {
            // 7. Cleanup: Close the main page and browser
            if ($page) {
                try {
                    $page->close();
                } catch (\Exception $e) {
                    $this->logger->warning('Could not close main search page: {error}', ['error' => $e->getMessage()]);
                }
            }
            if ($browser) {
                try {
                    $this->logger->info('Closing browser connection.');
                    $browser->close();
                } catch (\Exception $e) {
                    $this->logger->error('Error closing browser: {error}', ['error' => $e->getMessage()]);
                }
            }
        }

        $this->logger->info('Tobacco search completed for keywords "{keywords}". Found {count} valid results.', [
            'keywords' => $keywords,
            'count' => count($results)
        ]);
        return $results;
    }

    /**
     * Helper to safely get text content from a selector.
     */
    private function getText(Page $page, string $selector): ?string
    {
        try {
            $element = $page->dom()->querySelector($selector);
            if ($element) {
                // Use evaluate for potentially more robust text extraction
                $text = $page->evaluate("document.querySelector('" . addslashes($selector) . "').innerText")->getReturnValue();
                return $text;
            }
            $this->logger->warning('Selector not found for getText: {selector}', ['selector' => $selector]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting text for selector {selector}: {error}', ['selector' => $selector, 'error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Helper to safely get an attribute value from a selector.
     */
    private function getAttribute(Page $page, string $selector, string $attribute): ?string
    {
        try {
            $element = $page->dom()->querySelector($selector);
            if ($element) {
                // Use evaluate for robust attribute fetching
                $value = $page->evaluate("document.querySelector('" . addslashes($selector) . "').getAttribute('" . addslashes($attribute) . "')")->getReturnValue();
                return $value; // Could be null if attribute doesn't exist
            }
            $this->logger->warning('Selector not found for getAttribute: {selector}', ['selector' => $selector]);
        } catch (\Exception $e) {
            $this->logger->error('Error getting attribute "{attr}" for selector {selector}: {error}', ['attr' => $attribute, 'selector' => $selector, 'error' => $e->getMessage()]);
        }
        return null;
    }


    /**
     * Simple check to determine if keywords likely represent a barcode.
     * Adjust logic as needed (e.g., check length, use regex for EAN/UPC).
     */
    private function isBarcode(string $keywords): bool
    {
        // Basic check: purely numeric and reasonable length (e.g., 8-14 digits)
        return ctype_digit($keywords) && strlen($keywords) >= 8 && strlen($keywords) <= 14;
    }
}
