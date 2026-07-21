<?php

declare(strict_types=1);

namespace App\Controller;

use App\Poster\PosterCategory;
use App\Poster\Upload\PosterUploader;
use App\Poster\Upload\UploadException;
use App\Support\Flash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Slim\Exception\HttpNotFoundException;

/**
 * Handles poster uploads from a local file and from a URL.
 */
final class UploadController
{
    public function __construct(
        private readonly PosterUploader $uploader,
        private readonly Flash $flash,
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function file(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $file = $request->getUploadedFiles()['poster'] ?? null;

        try {
            if (!$file instanceof UploadedFileInterface) {
                throw UploadException::failed();
            }
            $this->uploader->fromUploadedFile($category, $file);
            $this->flash->add('success', 'Poster uploaded.');
        } catch (UploadException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $this->backToCategory($response, $category);
    }

    /**
     * @param array<string, string> $args
     */
    public function url(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $category = $this->requireCategory($request, $args);
        $body = (array) $request->getParsedBody();
        $url = isset($body['url']) && is_string($body['url']) ? $body['url'] : '';

        try {
            $this->uploader->fromUrl($category, $url);
            $this->flash->add('success', 'Poster added from URL.');
        } catch (UploadException $e) {
            $this->flash->add('error', $e->getMessage());
        }

        return $this->backToCategory($response, $category);
    }

    /**
     * @param array<string, string> $args
     */
    private function requireCategory(ServerRequestInterface $request, array $args): PosterCategory
    {
        $category = PosterCategory::fromSlug($args['category'] ?? '');
        if ($category === null) {
            throw new HttpNotFoundException($request);
        }

        return $category;
    }

    private function backToCategory(ResponseInterface $response, PosterCategory $category): ResponseInterface
    {
        return $response->withHeader('Location', '/library/' . $category->value)->withStatus(302);
    }
}
