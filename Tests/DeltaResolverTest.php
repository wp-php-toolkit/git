<?php

namespace WordPress\Git\Tests;

use WordPress\ByteStream\MemoryPipe;
use WordPress\ByteStream\Reader\ReaderUtils;
use WordPress\Git\GitObjectReader;
use WordPress\Git\Protocol\Parser\DeltaResolver;
use WordPress\Git\Protocol\Writers\PackWriter;

class DeltaResolverTest extends \PHPUnit\Framework\TestCase {

    public function test_resolve_next_chunk() {
        $string_reader = new MemoryPipe();
        $base_bytes = "Hello, world!";
        $pack_writer = new PackWriter($string_reader);
        $pack_writer->append_object_header('blob', strlen($base_bytes));
        $pack_writer->append_bytes($base_bytes);
        $pack_writer->flush_object_body();
        $string_reader->seek(0);
        $encoded_base_bytes = ReaderUtils::read_all_remaining_bytes($string_reader);
        $pack_writer->close();

        $base_reader = new GitObjectReader(new MemoryPipe(
            $encoded_base_bytes
        ));

        $resolved_chunk = "World? Hello, I am changed!";
        $delta_bytes = implode('', [
            PackWriter::encode_variable_length(strlen($base_bytes)),
            PackWriter::encode_variable_length(strlen($resolved_chunk)),
            // The leftmost bit is 0 = we're consuming from the delta
            // The next 7 bits amount to 0b110 = we're consuming the next 6 bytes
            chr(0b00000110),
            "World?",

            // The leftmost bit is 1 = we're copying from the base
            // The next 3 bits are 001 = we're encoding the copy length as 1 byte
            // The next 4 bits are 0000 = we're encoding the offset as 0 bytes
            chr(0b10010000),
            // 7 – the number of bytes from the base to copy
            chr(7),

            // The leftmost bit is 0 = we're consuming from the delta
            // The next 7 bits amount to 0b110 = we're consuming the next 13 bytes
            chr(0b00001101),
            "I am changed!",
        ]);
        $delta_reader = new MemoryPipe($delta_bytes);

        $resolver = new DeltaResolver($base_reader, $delta_reader);
        $this->assertTrue($resolver->resolve_next_chunk());
        $this->assertEquals('World?', $resolver->get_resolved_chunk());

        $this->assertTrue($resolver->resolve_next_chunk());
        $this->assertEquals('Hello, ', $resolver->get_resolved_chunk());

        $this->assertTrue($resolver->resolve_next_chunk());
        $this->assertEquals('I am changed!', $resolver->get_resolved_chunk());

        $this->assertFalse($resolver->resolve_next_chunk());
    }

}
