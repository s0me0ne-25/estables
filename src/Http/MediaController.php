<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Estables\Http;

use Estables\Core\HttpException;
use Estables\Core\Request;
use Estables\Core\Response;
use Estables\Core\Services;

/**
 * Image files the web server does not have yet, and "Скачать оригинал".
 *
 * nginx serves everything under /media/ that exists on disk; a missing copy
 * of an allowed size reaches the engine, which makes it, stores it next to
 * the others and answers with it. Downloads go through the engine only to
 * check the image and set a readable file name; the file itself is sent by
 * nginx (X-Accel-Redirect).
 */
final class MediaController
{
    private const LONG_CACHE = 'public, max-age=31536000, immutable';

    public function __construct(private readonly Services $services) {}

    /**
     * /media/<size>/<ab>/<sha>.<ext>
     *
     * @param array<string, string> $params
     */
    public function copy(Request $request, array $params): Response
    {
        $media = $this->services->media();
        $size = (int) $params['size'];
        $image = $this->services->images()->find($params['sha']);
        if ($image === null || $image->ext !== $params['ext'] || substr($image->sha, 0, 2) !== $params['dir']
            || !\in_array($size, $media->sizes(), true)) {
            throw HttpException::notFound();
        }
        $path = $this->services->thumbnailer()->copy($image, $size);

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => $image->mime(),
            'Cache-Control' => self::LONG_CACHE,
            // Like the copies nginx serves later: other sites may show them.
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }

    /**
     * /download/<sha>/<name>
     *
     * @param array<string, string> $params
     */
    public function download(Request $request, array $params): Response
    {
        $image = $this->services->images()->find($params['sha']);
        $name = $params['name'];
        if ($image === null || !preg_match('~^[A-Za-z0-9][A-Za-z0-9._-]{0,150}\.' . $image->ext . '$~', $name)) {
            throw HttpException::notFound();
        }

        return new Response('', 200, [
            'Content-Type' => $image->mime(),
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'X-Accel-Redirect' => $this->services->media()->originalUrl($image),
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
