<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Citation extends Model
{
    protected $fillable = [
        'citation_key',
        'doi',
        'pubmed_id',
        'arxiv_id',
        'authors',
        'title',
        'journal',
        'conference',
        'year',
        'volume',
        'issue',
        'pages',
        'publisher',
        'verification_status',
        'confidence_score',
        'verification_sources',
        'last_verified_at',
        'apa_format',
        'mla_format',
        'chicago_format',
        'harvard_format',
        'abstract',
        'keywords',
        'url',
    ];

    protected $casts = [
        'authors' => 'array',
        'verification_sources' => 'array',
        'keywords' => 'array',
        'confidence_score' => 'decimal:2',
        'last_verified_at' => 'datetime',
    ];

    /**
     * Get document citations that use this citation
     */
    public function documentCitations(): HasMany
    {
        return $this->hasMany(DocumentCitation::class);
    }

    /**
     * Get verification attempts for this citation
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(CitationVerification::class, 'matched_citation_id');
    }

    /**
     * Check if citation is verified with high confidence
     */
    public function isHighConfidence(): bool
    {
        return $this->verification_status === 'verified' && $this->confidence_score >= 0.85;
    }

    /**
     * Get formatted citation for specific style
     */
    public function getFormattedCitation(string $style = 'apa'): string
    {
        return match ($style) {
            'apa' => $this->apa_format ?: $this->generateApaFormat(),
            'mla' => $this->mla_format ?: $this->generateMlaFormat(),
            'chicago' => $this->chicago_format ?: $this->generateChicagoFormat(),
            'harvard' => $this->harvard_format ?: $this->generateHarvardFormat(),
            default => $this->apa_format ?: $this->generateApaFormat(),
        };
    }

    /**
     * Generate APA format citation
     */
    private function generateApaFormat(): string
    {
        $authors = is_array($this->authors) ? implode(', ', $this->authors) : $this->authors;

        $citation = "{$authors} ({$this->year}). {$this->title}";

        if ($this->journal) {
            $citation .= ". {$this->journal}";
            if ($this->volume) {
                $citation .= ", {$this->volume}";
            }
            if ($this->issue) {
                $citation .= "({$this->issue})";
            }
            if ($this->pages) {
                $citation .= ", {$this->pages}";
            }
        } elseif ($this->conference) {
            $citation .= ". In {$this->conference}";
            if ($this->pages) {
                $citation .= " (pp. {$this->pages})";
            }
        }

        if ($this->publisher) {
            $citation .= ". {$this->publisher}";
        }
        if ($this->doi) {
            $citation .= ". https://doi.org/{$this->doi}";
        }

        $citation .= '.';

        // Cache the generated format
        $this->update(['apa_format' => $citation]);

        return $citation;
    }

    /**
     * Generate MLA format citation
     */
    private function generateMlaFormat(): string
    {
        $authors = is_array($this->authors) ? implode(', ', $this->authors) : $this->authors;

        $citation = "{$authors}. \"{$this->title}.\"";

        if ($this->journal) {
            $citation .= " {$this->journal}";
            if ($this->volume) {
                $citation .= ", vol. {$this->volume}";
            }
            if ($this->issue) {
                $citation .= ", no. {$this->issue}";
            }
            $citation .= ", {$this->year}";
            if ($this->pages) {
                $citation .= ", pp. {$this->pages}";
            }
        }

        if ($this->doi) {
            $citation .= ", doi:{$this->doi}";
        }

        $citation .= '.';

        // Cache the generated format
        $this->update(['mla_format' => $citation]);

        return $citation;
    }

    /**
     * Generate Chicago format citation
     */
    private function generateChicagoFormat(): string
    {
        $authors = is_array($this->authors) ? implode(', ', $this->authors) : $this->authors;

        $citation = "{$authors}. \"{$this->title}.\"";

        if ($this->journal) {
            $citation .= " {$this->journal}";
            if ($this->volume) {
                $citation .= " {$this->volume}";
            }
            if ($this->issue) {
                $citation .= ", no. {$this->issue}";
            }
            $citation .= " ({$this->year})";
            if ($this->pages) {
                $citation .= ": {$this->pages}";
            }
        }

        if ($this->doi) {
            $citation .= ". https://doi.org/{$this->doi}";
        }

        $citation .= '.';

        // Cache the generated format
        $this->update(['chicago_format' => $citation]);

        return $citation;
    }

    /**
     * Generate Harvard format citation
     */
    private function generateHarvardFormat(): string
    {
        $authors = is_array($this->authors) ? implode(', ', $this->authors) : $this->authors;

        $citation = "{$authors} ({$this->year}) '{$this->title}'";

        if ($this->journal) {
            $citation .= ", {$this->journal}";
            if ($this->volume) {
                $citation .= ", {$this->volume}";
            }
            if ($this->issue) {
                $citation .= "({$this->issue})";
            }
            if ($this->pages) {
                $citation .= ", pp. {$this->pages}";
            }
        }

        if ($this->doi) {
            $citation .= ", doi: {$this->doi}";
        }

        $citation .= '.';

        // Cache the generated format
        $this->update(['harvard_format' => $citation]);

        return $citation;
    }

    /**
     * Scope for verified citations
     */
    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'verified');
    }

    /**
     * Scope for high confidence citations
     */
    public function scopeHighConfidence($query)
    {
        return $query->where('verification_status', 'verified')
            ->where('confidence_score', '>=', 0.85);
    }

    /**
     * Scope for recent citations
     */
    public function scopeRecentlyVerified($query, $days = 30)
    {
        return $query->where('last_verified_at', '>=', now()->subDays($days));
    }

    /**
     * Search citations by text
     */
    public function scopeSearch($query, $searchText)
    {
        return $query->where(function ($q) use ($searchText) {
            $q->where('title', 'LIKE', "%{$searchText}%")
                ->orWhere('abstract', 'LIKE', "%{$searchText}%")
                ->orWhereJsonContains('authors', $searchText);
        });
    }
}
