<?php

namespace WordPress\Git\Tests;

use WordPress\Filesystem\InMemoryFilesystem;
use WordPress\Git\GitRepository;
use WordPress\Git\Protocol\Parser\GitProtocolReader;

class GitProtocolReaderTest extends \PHPUnit\Framework\TestCase {

    public function test_protocol_reader_wordpress_develop() {
        $repo = new GitRepository(InMemoryFilesystem::create());
        $reader = new GitProtocolReader(['write_to_repository' => $repo]);
        $reader->append_bytes(file_get_contents(__DIR__ . '/fixtures/wordpress-develop-response-no-blobs.bin'));

        while($reader->next_token()) {
            $reader->get_token_type();
        }

        // We just want to see there are no exceptions thrown
        $this->assertTrue(true);
    }
}
