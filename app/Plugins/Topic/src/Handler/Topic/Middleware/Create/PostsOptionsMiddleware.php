<?php

declare(strict_types=1);
/**
 * This file is part of zhuchunshu.
 * @link     https://github.com/zhuchunshu
 * @document https://github.com/zhuchunshu/super-forum
 * @contact  laravel@88.com
 * @license  https://github.com/zhuchunshu/super-forum/blob/master/LICENSE
 */
namespace App\Plugins\Topic\src\Handler\Topic\Middleware\Create;

use App\Plugins\Core\src\Models\PostsOption;
use App\Plugins\Topic\src\Handler\Topic\Middleware\MiddlewareInterface;

#[\App\Plugins\Topic\src\Annotation\Topic\CreateLastMiddleware]
class PostsOptionsMiddleware implements MiddlewareInterface
{
    public function handler($data, \Closure $next)
    {
        $post_id = $data['post_id'];
        if (! PostsOption::query()->where('post_id', $post_id)->exists()) {
            $post_options = PostsOption::create([
                'post_id' => $post_id,
            ]);
        } else {
            $post_options = PostsOption::query()->where('post_id', $post_id)->first()->id;
        }
        $data['posts_options'] = $post_options;
        return $next($data);
    }
}
