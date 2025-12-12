<?php

namespace App\Services\PromptSystem;

class ContentRequirements
{
    public function __construct(
        public array $tables = [],
        public array $diagrams = [],
        public array $calculations = [],
        public array $code = [],
        public array $mockData = [],
        public array $placeholders = [],
        public array $tools = [],
        public array $citations = [],
        public array $formatting = []
    ) {}

    /**
     * Get all table requirements
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * Get required tables only
     */
    public function getRequiredTables(): array
    {
        return array_filter($this->tables, fn ($table) => $table['required'] ?? false);
    }

    /**
     * Get tables that need mock data
     */
    public function getMockDataTables(): array
    {
        return array_filter($this->tables, fn ($table) => $table['mock_data'] ?? false);
    }

    /**
     * Get all diagram requirements
     */
    public function getDiagrams(): array
    {
        return $this->diagrams;
    }

    /**
     * Get required diagrams only
     */
    public function getRequiredDiagrams(): array
    {
        return array_filter($this->diagrams, fn ($diagram) => $diagram['required'] ?? false);
    }

    /**
     * Get diagrams that need placeholders (can't be AI-generated)
     */
    public function getPlaceholderDiagrams(): array
    {
        return array_filter($this->diagrams, fn ($diagram) => $diagram['needs_placeholder'] ?? false);
    }

    /**
     * Check if code is required for this chapter
     */
    public function requiresCode(): bool
    {
        return ! empty($this->code) && ($this->code['required'] ?? false);
    }

    /**
     * Get code language if code is required
     */
    public function getCodeLanguage(): ?string
    {
        return $this->code['language'] ?? null;
    }

    /**
     * Check if calculations are required
     */
    public function requiresCalculations(): bool
    {
        return ! empty($this->calculations);
    }

    /**
     * Get minimum table count
     */
    public function getMinimumTableCount(): int
    {
        return array_sum(array_map(fn ($t) => $t['min'] ?? 1, $this->getRequiredTables()));
    }

    /**
     * Get minimum diagram count
     */
    public function getMinimumDiagramCount(): int
    {
        return array_sum(array_map(fn ($d) => $d['min'] ?? 1, $this->getRequiredDiagrams()));
    }

    /**
     * Merge with another ContentRequirements (for template inheritance)
     */
    public function merge(ContentRequirements $other): self
    {
        return new self(
            tables: array_merge($this->tables, $other->tables),
            diagrams: array_merge($this->diagrams, $other->diagrams),
            calculations: array_merge($this->calculations, $other->calculations),
            code: array_merge($this->code, $other->code),
            mockData: array_merge($this->mockData, $other->mockData),
            placeholders: array_merge($this->placeholders, $other->placeholders),
            tools: array_merge($this->tools, $other->tools),
            citations: array_merge($this->citations, $other->citations),
            formatting: array_merge($this->formatting, $other->formatting)
        );
    }

    /**
     * Convert to array for JSON storage
     */
    public function toArray(): array
    {
        return [
            'tables' => $this->tables,
            'diagrams' => $this->diagrams,
            'calculations' => $this->calculations,
            'code' => $this->code,
            'mockData' => $this->mockData,
            'placeholders' => $this->placeholders,
            'tools' => $this->tools,
            'citations' => $this->citations,
            'formatting' => $this->formatting,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tables: $data['tables'] ?? [],
            diagrams: $data['diagrams'] ?? [],
            calculations: $data['calculations'] ?? [],
            code: $data['code'] ?? [],
            mockData: $data['mockData'] ?? [],
            placeholders: $data['placeholders'] ?? [],
            tools: $data['tools'] ?? [],
            citations: $data['citations'] ?? [],
            formatting: $data['formatting'] ?? []
        );
    }
}
