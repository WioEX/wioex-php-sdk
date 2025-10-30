<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;
use Wioex\SDK\Exceptions\ValidationException;
use Wioex\SDK\Exceptions\RequestException;

class Logos extends Resource
{
    /**
     * Get logo URL for a specific stock symbol
     * 
     * @param string $symbol Stock symbol (e.g., "AAPL", "GOOGL")
     * @return string Logo URL
     * 
     * @example
     * ```php
     * $logoUrl = $client->logos()->getUrl('AAPL');
     * echo "<img src='{$logoUrl}' alt='AAPL Logo'>";
     * ```
     */
    public function getUrl(string $symbol): string
    {
        $this->validateSymbol($symbol);
        $symbol = strtoupper(trim($symbol));
        
        return $this->client->getConfig()->getBaseUrl() . "/logos/{$symbol}";
    }

    /**
     * Get logo URLs for multiple symbols in batch
     * 
     * @param array $symbols Array of stock symbols
     * @return array Associative array where keys are symbols and values are logo URLs
     * 
     * @example
     * ```php
     * $symbols = ['AAPL', 'GOOGL', 'MSFT'];
     * $logoUrls = $client->logos()->getBatch($symbols);
     * foreach ($logoUrls as $symbol => $url) {
     *     echo "<img src='{$url}' alt='{$symbol} Logo'>";
     * }
     * ```
     */
    public function getBatch(array $symbols): array
    {
        if (empty($symbols)) {
            throw new ValidationException('Symbols array cannot be empty');
        }

        if (count($symbols) > 100) {
            throw new ValidationException('Maximum 100 symbols allowed per batch request');
        }

        // Validate all symbols
        foreach ($symbols as $symbol) {
            $this->validateSymbol($symbol);
        }

        $symbolsString = implode(',', array_map('strtoupper', array_map('trim', $symbols)));
        $response = $this->get('/logos/batch', ['symbols' => $symbolsString]);
        
        $data = $response->getData();
        $logoUrls = [];
        
        if (isset($data['logos']) && is_array($data['logos'])) {
            foreach ($data['logos'] as $symbol => $logoInfo) {
                $logoUrls[$symbol] = $logoInfo['logo_url'] ?: null;
            }
        }
        
        return $logoUrls;
    }

    /**
     * Download logo data as binary content
     * 
     * @param string $symbol Stock symbol
     * @return string Binary image data
     * 
     * @throws RequestException If logo not found or download fails
     * 
     * @example
     * ```php
     * $logoData = $client->logos()->download('AAPL');
     * file_put_contents('aapl_logo.png', $logoData);
     * ```
     */
    public function download(string $symbol): string
    {
        $this->validateSymbol($symbol);
        $symbol = strtoupper(trim($symbol));
        
        try {
            // Make direct HTTP request to logo endpoint
            $logoUrl = "/logos/{$symbol}";
            $response = $this->client->getRawResponse('GET', $logoUrl);
            
            if ($response->getStatusCode() !== 200) {
                throw new RequestException("Logo not found for symbol: {$symbol}");
            }
            
            return $response->getBody()->getContents();
            
        } catch (\Exception $e) {
            throw new RequestException("Failed to download logo for {$symbol}: " . $e->getMessage());
        }
    }

    /**
     * Get logo information and metadata
     * 
     * @param string $symbol Stock symbol
     * @return Response Logo information including availability, file size, etc.
     * 
     * @example
     * ```php
     * $logoInfo = $client->logos()->getInfo('AAPL');
     * if ($logoInfo->getData()['logo_available']) {
     *     echo "Logo URL: " . $logoInfo->getData()['logo_url'];
     *     echo "File Size: " . $logoInfo->getData()['file_size'] . " bytes";
     * }
     * ```
     */
    public function getInfo(string $symbol): Response
    {
        $this->validateSymbol($symbol);
        $symbol = strtoupper(trim($symbol));
        
        return $this->get("/logos/{$symbol}/info");
    }

    /**
     * Check if logo exists for a symbol
     * 
     * @param string $symbol Stock symbol
     * @return bool True if logo exists, false otherwise
     * 
     * @example
     * ```php
     * if ($client->logos()->exists('AAPL')) {
     *     $logoUrl = $client->logos()->getUrl('AAPL');
     *     echo "<img src='{$logoUrl}' alt='AAPL Logo'>";
     * } else {
     *     echo "No logo available for AAPL";
     * }
     * ```
     */
    public function exists(string $symbol): bool
    {
        try {
            $logoInfo = $this->getInfo($symbol);
            $data = $logoInfo->getData();
            return $data['logo_available'] ?? false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get list of all available logo symbols
     * 
     * @return Response Response containing array of available symbol names
     * 
     * @example
     * ```php
     * $availableLogos = $client->logos()->getAvailable();
     * $symbols = $availableLogos->getData()['symbols'];
     * echo "Available logos: " . implode(', ', array_slice($symbols, 0, 10));
     * ```
     */
    public function getAvailable(): Response
    {
        return $this->get('/logos/available');
    }

    /**
     * Get detailed batch information for multiple symbols
     * 
     * @param array $symbols Array of stock symbols
     * @return Response Detailed information about each logo including availability and metadata
     * 
     * @example
     * ```php
     * $symbols = ['AAPL', 'GOOGL', 'INVALID_SYMBOL'];
     * $batchInfo = $client->logos()->getBatchInfo($symbols);
     * $logos = $batchInfo->getData()['logos'];
     * 
     * foreach ($logos as $symbol => $info) {
     *     if ($info['logo_available']) {
     *         echo "{$symbol}: Logo available ({$info['file_size']} bytes)\n";
     *     } else {
     *         echo "{$symbol}: No logo available\n";
     *     }
     * }
     * ```
     */
    public function getBatchInfo(array $symbols): Response
    {
        if (empty($symbols)) {
            throw new ValidationException('Symbols array cannot be empty');
        }

        if (count($symbols) > 100) {
            throw new ValidationException('Maximum 100 symbols allowed per batch request');
        }

        // Validate all symbols
        foreach ($symbols as $symbol) {
            $this->validateSymbol($symbol);
        }

        $symbolsString = implode(',', array_map('strtoupper', array_map('trim', $symbols)));
        return $this->get('/logos/batch', ['symbols' => $symbolsString]);
    }

    /**
     * Save logo to local file
     * 
     * @param string $symbol Stock symbol
     * @param string $filePath Local file path where to save the logo
     * @return bool True if successfully saved, false otherwise
     * 
     * @example
     * ```php
     * if ($client->logos()->saveToFile('AAPL', '/tmp/aapl_logo.png')) {
     *     echo "Logo saved successfully!";
     * } else {
     *     echo "Failed to save logo";
     * }
     * ```
     */
    public function saveToFile(string $symbol, string $filePath): bool
    {
        try {
            $logoData = $this->download($symbol);
            
            // Create directory if it doesn't exist
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            return file_put_contents($filePath, $logoData) !== false;
            
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get logos for multiple symbols with automatic filtering of available ones
     * 
     * @param array $symbols Array of stock symbols
     * @param bool $includeUnavailable Whether to include unavailable logos in result (default: false)
     * @return array Array of logo URLs for available symbols only
     * 
     * @example
     * ```php
     * $symbols = ['AAPL', 'GOOGL', 'INVALID_SYMBOL'];
     * $availableLogos = $client->logos()->getAvailableOnly($symbols);
     * // Returns only URLs for AAPL and GOOGL if INVALID_SYMBOL doesn't have a logo
     * ```
     */
    public function getAvailableOnly(array $symbols, bool $includeUnavailable = false): array
    {
        $batchInfo = $this->getBatchInfo($symbols);
        $logos = $batchInfo->getData()['logos'] ?? [];
        $result = [];

        foreach ($logos as $symbol => $info) {
            if ($info['logo_available']) {
                $result[$symbol] = $info['logo_url'];
            } elseif ($includeUnavailable) {
                $result[$symbol] = null;
            }
        }

        return $result;
    }

    /**
     * Validate stock symbol format
     * 
     * @param string $symbol Stock symbol to validate
     * @throws ValidationException If symbol format is invalid
     */
    private function validateSymbol(string $symbol): void
    {
        if (empty($symbol)) {
            throw new ValidationException('Symbol cannot be empty');
        }

        if (strlen($symbol) > 10) {
            throw new ValidationException('Symbol cannot be longer than 10 characters');
        }

        if (!preg_match('/^[A-Za-z0-9._-]+$/', $symbol)) {
            throw new ValidationException('Symbol contains invalid characters. Only letters, numbers, dots, underscores, and hyphens are allowed');
        }
    }
}