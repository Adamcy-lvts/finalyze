<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WordPackage extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'tier',
        'words',
        'price',
        'currency',
        'description',
        'features',
        'sort_order',
        'is_active',
        'is_popular',
        'metadata',
    ];

    protected $casts = [
        'words' => 'integer',
        'price' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'features' => 'array',
        'metadata' => 'array',
    ];

    // =========================================================================
    // CONSTANTS
    // =========================================================================

    public const TYPE_PROJECT = 'project';

    public const TYPE_TOPUP = 'topup';

    public const TIER_UNDERGRADUATE = 'undergraduate';

    public const TIER_POSTGRADUATE = 'postgraduate';

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeProjects($query)
    {
        return $query->where('type', self::TYPE_PROJECT);
    }

    public function scopeTopups($query)
    {
        return $query->where('type', self::TYPE_TOPUP);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Get price in Naira (from kobo)
     */
    public function getPriceInNairaAttribute(): float
    {
        return $this->price / 100;
    }

    /**
     * Get formatted price string
     */
    public function getFormattedPriceAttribute(): string
    {
        return '₦'.number_format($this->price_in_naira, 0);
    }

    /**
     * Get formatted word count
     */
    public function getFormattedWordsAttribute(): string
    {
        return number_format($this->words);
    }

    /**
     * Get price per word (in kobo)
     */
    public function getPricePerWordAttribute(): float
    {
        return $this->words > 0 ? $this->price / $this->words : 0;
    }

    /**
     * Check if this is a project package
     */
    public function getIsProjectAttribute(): bool
    {
        return $this->type === self::TYPE_PROJECT;
    }

    /**
     * Check if this is a top-up package
     */
    public function getIsTopupAttribute(): bool
    {
        return $this->type === self::TYPE_TOPUP;
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Get package data for frontend
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'tier' => $this->tier,
            'words' => $this->words,
            'formatted_words' => $this->formatted_words,
            'price' => $this->price,
            'price_in_naira' => $this->price_in_naira,
            'formatted_price' => $this->formatted_price,
            'description' => $this->description,
            'features' => $this->features ?? [],
            'is_popular' => $this->is_popular,
        ];
    }

    // =========================================================================
    // STATIC HELPERS
    // =========================================================================

    /**
     * Get all packages for pricing page
     */
    public static function getForPricingPage(): array
    {
        $packages = self::active()->ordered()->get();

        return [
            'projects' => $packages->where('type', self::TYPE_PROJECT)->values()->map->toFrontendArray(),
            'topups' => $packages->where('type', self::TYPE_TOPUP)->values()->map->toFrontendArray(),
        ];
    }

    /**
     * Find package by slug
     */
    public static function findBySlug(string $slug): ?self
    {
        return self::where('slug', $slug)->first();
    }
}
