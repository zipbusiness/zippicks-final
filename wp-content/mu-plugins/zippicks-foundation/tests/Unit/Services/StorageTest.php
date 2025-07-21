<?php
/**
 * Storage Service Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Storage\FilesystemInterface;
use ZipPicks\Foundation\Storage\LocalFilesystem;
use ZipPicks\Foundation\Services\StorageServiceProvider;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class StorageTest extends TestCase
{
    private vfsStreamDirectory $root;
    private string $basePath;
    private LocalFilesystem $filesystem;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up virtual filesystem
        $this->root = vfsStream::setup('storage');
        $this->basePath = vfsStream::url('storage');
        $this->filesystem = new LocalFilesystem($this->basePath);
    }

    public function testWrite(): void
    {
        $result = $this->filesystem->write('test.txt', 'Hello World');
        
        $this->assertTrue($result);
        $this->assertTrue($this->root->hasChild('test.txt'));
        $this->assertEquals('Hello World', $this->root->getChild('test.txt')->getContent());
    }

    public function testRead(): void
    {
        vfsStream::newFile('test.txt')
            ->withContent('Test content')
            ->at($this->root);

        $content = $this->filesystem->read('test.txt');
        
        $this->assertEquals('Test content', $content);
    }

    public function testReadNonExistentFile(): void
    {
        $content = $this->filesystem->read('nonexistent.txt');
        
        $this->assertNull($content);
    }

    public function testExists(): void
    {
        vfsStream::newFile('exists.txt')->at($this->root);

        $this->assertTrue($this->filesystem->exists('exists.txt'));
        $this->assertFalse($this->filesystem->exists('notexists.txt'));
    }

    public function testDelete(): void
    {
        vfsStream::newFile('delete.txt')->at($this->root);

        $this->assertTrue($this->filesystem->exists('delete.txt'));
        
        $result = $this->filesystem->delete('delete.txt');
        
        $this->assertTrue($result);
        $this->assertFalse($this->filesystem->exists('delete.txt'));
    }

    public function testDeleteNonExistentFile(): void
    {
        // Deleting non-existent file should return true
        $result = $this->filesystem->delete('nonexistent.txt');
        
        $this->assertTrue($result);
    }

    public function testCopy(): void
    {
        vfsStream::newFile('source.txt')
            ->withContent('Copy me')
            ->at($this->root);

        $result = $this->filesystem->copy('source.txt', 'destination.txt');
        
        $this->assertTrue($result);
        $this->assertTrue($this->filesystem->exists('source.txt'));
        $this->assertTrue($this->filesystem->exists('destination.txt'));
        $this->assertEquals('Copy me', $this->filesystem->read('destination.txt'));
    }

    public function testCopyNonExistentFile(): void
    {
        $result = $this->filesystem->copy('nonexistent.txt', 'destination.txt');
        
        $this->assertFalse($result);
        $this->assertFalse($this->filesystem->exists('destination.txt'));
    }

    public function testMove(): void
    {
        vfsStream::newFile('source.txt')
            ->withContent('Move me')
            ->at($this->root);

        $result = $this->filesystem->move('source.txt', 'destination.txt');
        
        $this->assertTrue($result);
        $this->assertFalse($this->filesystem->exists('source.txt'));
        $this->assertTrue($this->filesystem->exists('destination.txt'));
        $this->assertEquals('Move me', $this->filesystem->read('destination.txt'));
    }

    public function testMoveToSamePath(): void
    {
        vfsStream::newFile('same.txt')
            ->withContent('Same file')
            ->at($this->root);

        $result = $this->filesystem->move('same.txt', 'same.txt');
        
        $this->assertTrue($result);
        $this->assertTrue($this->filesystem->exists('same.txt'));
        $this->assertEquals('Same file', $this->filesystem->read('same.txt'));
    }

    public function testMakeDirectory(): void
    {
        $result = $this->filesystem->makeDirectory('new/nested/directory');
        
        $this->assertTrue($result);
        $this->assertTrue($this->root->hasChild('new'));
        $this->assertTrue($this->root->getChild('new')->hasChild('nested'));
        $this->assertTrue($this->root->getChild('new')->getChild('nested')->hasChild('directory'));
    }

    public function testMakeDirectoryAlreadyExists(): void
    {
        vfsStream::newDirectory('existing')->at($this->root);

        $result = $this->filesystem->makeDirectory('existing');
        
        $this->assertTrue($result);
    }

    public function testListFiles(): void
    {
        // Create directory structure
        vfsStream::newDirectory('dir1')->at($this->root);
        vfsStream::newDirectory('dir2')->at($this->root);
        vfsStream::newFile('file1.txt')->at($this->root);
        vfsStream::newFile('file2.txt')->at($this->root->getChild('dir1'));
        vfsStream::newFile('file3.txt')->at($this->root->getChild('dir2'));

        $files = $this->filesystem->listFiles('');
        
        $this->assertCount(3, $files);
        $this->assertContains('file1.txt', $files);
        $this->assertContains('dir1/file2.txt', $files);
        $this->assertContains('dir2/file3.txt', $files);
    }

    public function testListFilesInSubdirectory(): void
    {
        vfsStream::newDirectory('subdir')->at($this->root);
        vfsStream::newFile('file1.txt')->at($this->root->getChild('subdir'));
        vfsStream::newFile('file2.txt')->at($this->root->getChild('subdir'));

        $files = $this->filesystem->listFiles('subdir');
        
        $this->assertCount(2, $files);
        $this->assertContains('subdir/file1.txt', $files);
        $this->assertContains('subdir/file2.txt', $files);
    }

    public function testListFilesNonExistentDirectory(): void
    {
        $files = $this->filesystem->listFiles('nonexistent');
        
        $this->assertEmpty($files);
    }

    public function testLastModified(): void
    {
        $file = vfsStream::newFile('modified.txt')->at($this->root);
        $expectedTime = time();
        $file->lastModified($expectedTime);

        $result = $this->filesystem->lastModified('modified.txt');
        
        $this->assertEquals($expectedTime, $result);
    }

    public function testLastModifiedNonExistentFile(): void
    {
        $result = $this->filesystem->lastModified('nonexistent.txt');
        
        $this->assertNull($result);
    }

    public function testFileSize(): void
    {
        vfsStream::newFile('sized.txt')
            ->withContent('12345')
            ->at($this->root);

        $result = $this->filesystem->fileSize('sized.txt');
        
        $this->assertEquals(5, $result);
    }

    public function testFileSizeNonExistentFile(): void
    {
        $result = $this->filesystem->fileSize('nonexistent.txt');
        
        $this->assertNull($result);
    }

    public function testWriteToNestedDirectory(): void
    {
        $result = $this->filesystem->write('nested/deep/file.txt', 'Nested content');
        
        $this->assertTrue($result);
        $this->assertTrue($this->filesystem->exists('nested/deep/file.txt'));
        $this->assertEquals('Nested content', $this->filesystem->read('nested/deep/file.txt'));
    }

    public function testDeleteDirectory(): void
    {
        vfsStream::newDirectory('deleteme')->at($this->root);
        vfsStream::newFile('file.txt')->at($this->root->getChild('deleteme'));

        $result = $this->filesystem->deleteDirectory('deleteme');
        
        $this->assertTrue($result);
        $this->assertFalse($this->filesystem->exists('deleteme'));
    }

    public function testExtension(): void
    {
        $this->assertEquals('txt', $this->filesystem->extension('file.txt'));
        $this->assertEquals('jpg', $this->filesystem->extension('image.jpg'));
        $this->assertEquals('', $this->filesystem->extension('noextension'));
    }

    public function testName(): void
    {
        $this->assertEquals('file', $this->filesystem->name('file.txt'));
        $this->assertEquals('archive.tar', $this->filesystem->name('archive.tar.gz'));
        $this->assertEquals('noextension', $this->filesystem->name('noextension'));
    }

    public function testPathNormalization(): void
    {
        // Test various path formats
        $this->filesystem->write('//double//slashes//file.txt', 'content');
        $this->assertTrue($this->filesystem->exists('double/slashes/file.txt'));

        $this->filesystem->write('\\windows\\style\\path.txt', 'content');
        $this->assertTrue($this->filesystem->exists('windows/style/path.txt'));
    }

    public function testDirectoryTraversalPrevention(): void
    {
        // Attempt directory traversal
        $this->filesystem->write('../../../etc/passwd', 'malicious');
        
        // File should be created without the traversal
        $this->assertFalse($this->filesystem->exists('../../../etc/passwd'));
        $this->assertTrue($this->filesystem->exists('etc/passwd'));
    }

    public function testServiceProviderRegistration(): void
    {
        // Define constants if not already defined
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new StorageServiceProvider($foundation);
        $provider->register();

        // Test that filesystem is registered
        $this->assertTrue($container->has(FilesystemInterface::class));
        $this->assertTrue($container->has('filesystem'));

        // Test that we can resolve the filesystem
        $filesystem = $container->get('filesystem');
        $this->assertInstanceOf(FilesystemInterface::class, $filesystem);
        $this->assertInstanceOf(LocalFilesystem::class, $filesystem);
    }

    public function testServiceProviderDoesNotOverwriteExistingAlias(): void
    {
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();

        // Pre-register a custom filesystem alias
        $customFilesystem = new LocalFilesystem($this->basePath);
        $container->instance('filesystem', $customFilesystem);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new StorageServiceProvider($foundation);
        $provider->register();

        // Test that the original filesystem alias was not overwritten
        $resolvedFilesystem = $container->get('filesystem');
        $this->assertSame($customFilesystem, $resolvedFilesystem);
    }

    public function testCopyToNestedDirectory(): void
    {
        vfsStream::newFile('source.txt')
            ->withContent('Copy to nested')
            ->at($this->root);

        $result = $this->filesystem->copy('source.txt', 'nested/dest/file.txt');
        
        $this->assertTrue($result);
        $this->assertTrue($this->filesystem->exists('nested/dest/file.txt'));
        $this->assertEquals('Copy to nested', $this->filesystem->read('nested/dest/file.txt'));
    }

    public function testMoveToNestedDirectory(): void
    {
        vfsStream::newFile('source.txt')
            ->withContent('Move to nested')
            ->at($this->root);

        $result = $this->filesystem->move('source.txt', 'nested/dest/file.txt');
        
        $this->assertTrue($result);
        $this->assertFalse($this->filesystem->exists('source.txt'));
        $this->assertTrue($this->filesystem->exists('nested/dest/file.txt'));
        $this->assertEquals('Move to nested', $this->filesystem->read('nested/dest/file.txt'));
    }

    public function testEmptyFileOperations(): void
    {
        // Write empty file
        $this->assertTrue($this->filesystem->write('empty.txt', ''));
        $this->assertTrue($this->filesystem->exists('empty.txt'));
        $this->assertEquals('', $this->filesystem->read('empty.txt'));
        $this->assertEquals(0, $this->filesystem->fileSize('empty.txt'));
    }
}