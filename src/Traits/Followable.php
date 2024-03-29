<?php

namespace Jtar\HyperfFollow\Traits;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\Database\Model\Relations\HasMany;
use Jtar\HyperfFollow\Traits\Follower as Follower;
trait Followable
{
    public function needsToApproveFollowRequests(): bool
    {
        return false;
    }

    public function rejectFollowRequestFrom(Model $follower): void
    {
        if (!in_array(Follower::class, \class_uses($follower))) {
            throw new \InvalidArgumentException('The model must use the Follower trait.');
        }

        $this->followables()->followedBy($follower)->get()->each->delete();
    }

    public function acceptFollowRequestFrom(Model $follower): void
    {
        if (!in_array(Follower::class, \class_uses($follower))) {
            throw new \InvalidArgumentException('The model must use the Follower trait.');
        }

        $this->followables()->followedBy($follower)->get()->each->update(['accepted_at' => date('y-m-d H:i:s')]);
    }

    public function isFollowedBy(Model $follower): bool
    {
        if (!in_array(Follower::class, \class_uses($follower))) {
            throw new \InvalidArgumentException('The model must use the Follower trait.');
        }

        if ($this->relationLoaded('followables')) {
            return $this->followables->whereNotNull('accepted_at')->contains($follower);
        }

        return $this->followables()->accepted()->followedBy($follower)->exists();
    }

    public function scopeOrderByFollowersCount($query, string $direction = 'desc')
    {
        return $query->withCount('followers')->orderBy('followers_count', $direction);
    }

    public function scopeOrderByFollowersCountDesc($query)
    {
        return $this->scopeOrderByFollowersCount($query, 'desc');
    }

    public function scopeOrderByFollowersCountAsc($query)
    {
        return $this->scopeOrderByFollowersCount($query, 'asc');
    }

    public function followables(): HasMany
    {
        /**
         * @var Model $this
         */
        return $this->hasMany(
            config('follow.followables_model', \Jtar\HyperfFollow\Followable::class),
            'followable_id',
        )->where('followable_type', $this->getMorphClass());
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(
            config('follow.user_model'),
            config('follow.followables_table', 'followables'),
            'followable_id',
            config('follow.user_foreign_key', 'user_id')
        )->where('followable_type', $this->getMorphClass())
            ->withPivot(['accepted_at']);
    }

    public function approvedFollowers(): BelongsToMany
    {
        return $this->followers()->whereNotNull('accepted_at');
    }

    public function notApprovedFollowers(): BelongsToMany
    {
        return $this->followers()->whereNull('accepted_at');
    }
}