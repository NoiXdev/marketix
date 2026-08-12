<?php

namespace App\Policies;

use App\Models\Crawl;
use App\Models\User;

class CrawlPolicy
{
    public function view(User $user, Crawl $crawl): bool
    {
        return $crawl->project !== null && $user->canAccessProject($crawl->project);
    }

    public function delete(User $user, Crawl $crawl): bool
    {
        return $this->view($user, $crawl);
    }
}
