<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Creation extends Model
{
    public const COLLABORATOR_ROLE_OWNER = 'owner';

    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'content',
        'link',
        'category',
        'category_id',
        'tags',
        'featured_image',
        'publication_status',
        'status',
        'progress',
        'is_public',
        'is_open_for_collaboration',
        'is_open_for_review',
    ];

    protected $casts = [
        'progress' => 'integer',
        'category_id' => 'integer',
        'tags' => 'array',
        'is_public' => 'boolean',
        'is_open_for_collaboration' => 'boolean',
        'is_open_for_review' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Creation $creation) {
            $titleChanged = $creation->isDirty('title');
            $slugMissing = trim((string) ($creation->slug ?? '')) === '';

            if (! $titleChanged && ! $slugMissing) {
                return;
            }

            $creation->slug = static::makeUniqueSlug(
                (string) ($creation->title ?? ''),
                (int) ($creation->id ?? 0)
            );
        });

        static::deleting(function (Creation $creation) {
            $paths = $creation->photos()
                ->pluck('path')
                ->filter(fn ($path) => is_string($path) && trim($path) !== '')
                ->values()
                ->all();

            if (empty($paths)) {
                return;
            }

            Storage::disk('public')->delete($paths);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categoryOption()
    {
        return $this->belongsTo(CreationCategory::class, 'category_id');
    }

    public function photos()
    {
        return $this->hasMany(CreationPhoto::class)->orderBy('sort_order');
    }

    public function appreciations()
    {
        return $this->hasMany(CreationAppreciation::class);
    }

    public function collaborators()
    {
        return $this->hasMany(CreationCollaborator::class)
            ->with('user:id,name,username,profile_photo')
            ->orderBy('created_at');
    }

    public function collaborationRequests()
    {
        return $this->hasMany(CreationCollaborationRequest::class)
            ->with([
                'requester:id,name,username,profile_photo,email',
                'processor:id,name,username',
            ])
            ->latest();
    }

    public function insights()
    {
        return $this->hasMany(CreationInsight::class);
    }

    public function finalReview()
    {
        return $this->hasOne(CreationReview::class);
    }

    public function peerReviews()
    {
        return $this->hasMany(CreationPeerReview::class)
            ->latest('reviewed_at');
    }

    public function reviewPublications()
    {
        return $this->hasMany(CreationReviewPublication::class)
            ->latest('published_at');
    }

    public function assignedReviewer()
    {
        return $this->belongsTo(User::class, 'assigned_reviewer_id');
    }

    public function assignedRubric()
    {
        return $this->belongsTo(Rubric::class, 'assigned_rubric_id');
    }

    public function topLevelInsights()
    {
        return $this->insights()->whereNull('parent_id');
    }

    public function scopePublicVisible($query)
    {
        return $query
            ->where('is_public', true)
            ->where('publication_status', 'publish');
    }

    public function isOwnedBy(int $userId): bool
    {
        return $userId > 0 && (int) $this->user_id === $userId;
    }

    public function isCollaborator(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($this->relationLoaded('collaborators')) {
            return $this->collaborators->contains(fn (CreationCollaborator $member) => (int) $member->user_id === $userId);
        }

        return $this->collaborators()
            ->where('user_id', $userId)
            ->exists();
    }

    public function collaboratorRoleFor(int $userId): ?string
    {
        if ($this->isOwnedBy($userId)) {
            return self::COLLABORATOR_ROLE_OWNER;
        }

        if ($userId <= 0) {
            return null;
        }

        if ($this->relationLoaded('collaborators')) {
            return $this->collaborators
                ->firstWhere('user_id', $userId)?->role;
        }

        return $this->collaborators()
            ->where('user_id', $userId)
            ->value('role');
    }

    public function canView(int $userId): bool
    {
        return (bool) $this->is_public
            || $this->isOwnedBy($userId)
            || $this->isCollaborator($userId);
    }

    public function canEdit(int $userId): bool
    {
        $role = $this->collaboratorRoleFor($userId);

        return in_array($role, [
            self::COLLABORATOR_ROLE_OWNER,
            CreationCollaborator::ROLE_EDITOR,
            CreationCollaborator::ROLE_CONTRIBUTOR,
        ], true);
    }

    public function canManageCollaboration(int $userId): bool
    {
        return $this->isOwnedBy($userId);
    }

    public static function makeUniqueSlug(string $title, int $ignoreId = 0): string
    {
        $baseSlug = Str::slug($title);
        if ($baseSlug === '') {
            $baseSlug = 'creation';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId > 0, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
