<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category_id',
        'organizer_id',
        'venue',
        'event_date',
        'start_time',
        'end_time',
        'max_capacity',
        'banner_path',
        'registration_deadline',
        'status',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_registrations')
            ->withTimestamps();
    }
}