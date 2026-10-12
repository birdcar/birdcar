<?php

namespace App\Models\Publishing;

enum PieceKind: string
{
    case BlogPost = 'blog_post';
    case LinkedinPost = 'linkedin_post';
    case BlueskyThread = 'bluesky_thread';
    case EmailBlurb = 'email_blurb';

    public function label(): string
    {
        return match ($this) {
            self::BlogPost => 'Blog post',
            self::LinkedinPost => 'LinkedIn post',
            self::BlueskyThread => 'Bluesky thread',
            self::EmailBlurb => 'Email blurb',
        };
    }

    public function isKit(): bool
    {
        return $this !== self::BlogPost;
    }
}
