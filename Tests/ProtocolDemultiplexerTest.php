<?php

namespace WordPress\Git\Tests;

use WordPress\Git\Protocol\Parser\ProtocolDemultiplexer;

class ProtocolDemultiplexerTest extends \PHPUnit\Framework\TestCase {

    public function test_parse_response_no_blobs() {
        $demuxer = new ProtocolDemultiplexer();
        $fp = fopen(__DIR__ . '/fixtures/wordpress-develop-response-no-blobs.bin', 'r');
        $chunks_counts = [];
        while(true) {
            $bytes = fread($fp, 1024);
            if($bytes === false || strlen($bytes) === 0) {
                break;
            }
            $demuxer->append_bytes($bytes);
            while($demuxer->next_chunk()) {
                if(!isset($chunks_counts[$demuxer->get_stream_code()])) {
                    $chunks_counts[$demuxer->get_stream_code()] = 0;
                }
                $chunks_counts[$demuxer->get_stream_code()]++;
            }
        }
        $this->assertEquals(
            array(
                'unknown' => 2,
                'progress' => 29,
                'side_band' => 5,
            ),
            $chunks_counts
        );
    }

    public function test_parse_full_response() {
        $demuxer = new ProtocolDemultiplexer();
        $fp = fopen(__DIR__ . '/fixtures/wordpress-develop-response-full.bin', 'r');
        $chunks_counts = [];
        while(true) {
            $bytes = fread($fp, 1024);
            if($bytes === false || strlen($bytes) === 0) {
                break;
            }
            $demuxer->append_bytes($bytes);
            while($demuxer->next_chunk()) {
                if(!isset($chunks_counts[$demuxer->get_stream_code()])) {
                    $chunks_counts[$demuxer->get_stream_code()] = 0;
                }
                $chunks_counts[$demuxer->get_stream_code()]++;
            }
        }
        $this->assertEquals(
            array(
                'unknown' => 2,
                'progress' => 106,
                'side_band' => 4286,
            ),
            $chunks_counts
        );
    }
}
