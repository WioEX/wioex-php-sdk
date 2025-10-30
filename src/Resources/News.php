<?php

declare(strict_types=1);

namespace Wioex\SDK\Resources;

use Wioex\SDK\Http\Response;

class News extends Resource
{
    /**
     * Get latest news articles for a specific stock
     */
    public function latest(string $ticker): Response
    {
        return $this->get('/api/news', ['ticker' => $ticker]);
    }

    /**
     * Get AI-powered company analysis and insights
     */
    public function companyAnalysis(string $ticker): Response
    {
        return $this->get('/api/companyAnalysis', ['ticker' => $ticker]);
    }
}
