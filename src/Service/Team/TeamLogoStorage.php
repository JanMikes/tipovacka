<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Exception\TeamLogoNotProcessable;
use App\Service\Identity\ProvideIdentity;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The one place a team logo becomes a stored file.
 *
 * Whatever an admin uploads (PNG / JPEG / WebP / GIF) is normalised to a single
 * shape — a transparent WebP fitting inside 256×256, twice the biggest coin we
 * render, i.e. retina-sharp everywhere — and written through the `team_logos`
 * Flysystem storage. `Team.logo` holds the STORAGE PATH („019….webp"); `url()`
 * turns it into something an <img> can use, so moving the storage elsewhere
 * (S3/R2) is a change in config/packages/flysystem.php and nothing else.
 */
final readonly class TeamLogoStorage
{
    /** Longest edge of the stored logo, in px. */
    private const int MAX_EDGE = 256;

    private const int WEBP_QUALITY = 88;

    public function __construct(
        private ProvideIdentity $identity,
        #[Autowire(service: 'team_logos.storage')]
        private FilesystemOperator $storage,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Stores the upload and returns its storage path, e.g. „019….webp".
     */
    public function store(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getPathname()));

        if (false === $source) {
            throw TeamLogoNotProcessable::undecodable($file->getClientOriginalName() ?: $file->getFilename());
        }

        $path = $this->identity->next()->toRfc4122().'.webp';

        try {
            $this->storage->write($path, $this->encodeWebp($source));
        } catch (FilesystemException $e) {
            throw TeamLogoNotProcessable::unwritable($path, $e);
        }

        return $path;
    }

    /**
     * Public URL of a stored logo — null in, null out, so templates can pipe
     * `team.logo` through it unconditionally.
     */
    public function url(?string $path): ?string
    {
        if (null === $path || '' === $path) {
            return null;
        }

        return $this->storage->publicUrl($path);
    }

    public function exists(string $path): bool
    {
        return $this->storage->fileExists($path);
    }

    /**
     * Deletes a stored logo. A file that is already gone must never fail the
     * request that replaced it, so a storage error is logged, not thrown.
     */
    public function remove(?string $path): void
    {
        if (null === $path || '' === $path) {
            return;
        }

        try {
            $this->storage->delete($path);
        } catch (FilesystemException $e) {
            $this->logger->warning('Nepodařilo se smazat logo týmu.', ['path' => $path, 'exception' => $e]);
        }
    }

    /**
     * Fits the image inside MAX_EDGE × MAX_EDGE keeping the aspect ratio and the
     * alpha channel — smaller images are never upscaled — and encodes it as WebP.
     */
    private function encodeWebp(\GdImage $source): string
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, self::MAX_EDGE / max($width, $height));

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $encoded = imagewebp($canvas, null, self::WEBP_QUALITY);
        $bytes = (string) ob_get_clean();

        if (false === $encoded || '' === $bytes) {
            throw TeamLogoNotProcessable::unencodable();
        }

        return $bytes;
    }
}
