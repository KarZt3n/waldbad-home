<?php

namespace App\Tests\Integration\Data\Media\Image;

use App\Data\Media\Image\LocalImageStorage;
use App\Data\Media\Library\UploadDirectory;
use App\Logic\Common\IdentifierGeneratorInterface;
use App\Logic\Media\Image\Model\ImageDimensions;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class LocalImageStorageDimensionsTest extends TestCase
{
    private string $uploadsDirectory;

    protected function setUp(): void
    {
        $this->uploadsDirectory = sys_get_temp_dir().'/waldbad-image-dimensions-'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->uploadsDirectory.'/media');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->uploadsDirectory);
    }

    public function testReadsDimensionsFromImageFiles(): void
    {
        file_put_contents($this->uploadsDirectory.'/media/sommer.png', $this->pngHeader(1200, 800));
        file_put_contents($this->uploadsDirectory.'/media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"/>');
        file_put_contents($this->uploadsDirectory.'/media/plan.pdf', '%PDF-1.4');

        $dimensions = $this->storage()->dimensions([
            '/uploads/media/sommer.png',
            '/uploads/media/logo.svg',
            '/uploads/media/plan.pdf',
            '/uploads/media/fehlt.jpg',
            '/uploads/../geheim.png',
            'https://example.test/bild.jpg',
        ]);

        self::assertEquals(['/uploads/media/sommer.png' => new ImageDimensions(1200, 800)], $dimensions);
    }

    private function storage(): LocalImageStorage
    {
        return new LocalImageStorage(
            $this->createStub(IdentifierGeneratorInterface::class),
            $this->createStub(EntityManagerInterface::class),
            new UploadDirectory($this->uploadsDirectory),
            $this->uploadsDirectory.'/media',
        );
    }

    /** Signatur und IHDR-Chunk genügen `getimagesize()` zum Lesen der Maße. */
    private function pngHeader(int $width, int $height): string
    {
        $ihdr = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

        return "\x89PNG\r\n\x1a\n".pack('N', 13).$ihdr.pack('N', crc32($ihdr));
    }
}
