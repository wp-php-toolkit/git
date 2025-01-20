<?php

namespace WordPress\Git\Tests;

use WordPress\Git\Protocol\Parser\PacketParser;
use WordPress\Git\Protocol\Parser\ProtocolDemultiplexer;

class PacketParserTest extends \PHPUnit\Framework\TestCase {

    public function test_parse_response_no_blobs() {
        $demuxer = new ProtocolDemultiplexer();
        $packet_parser = new PacketParser();
        $fp = fopen(__DIR__ . '/fixtures/wordpress-develop-response-no-blobs.bin', 'r');
        $token_types = [];
        $packet_types = [];
        while(true) {
            $bytes = fread($fp, 1024);
            if($bytes === false || strlen($bytes) === 0) {
                break;
            }
            $demuxer->append_bytes($bytes);
            while($demuxer->next_chunk()) {
                switch($demuxer->get_stream_code()) {
                    case 'unknown':
                    case 'side_band':
                        $packet_parser->append_bytes($demuxer->get_chunk());
                        break;
                }
            }
            while($packet_parser->next_token()) {
                $token_types[] = $packet_parser->get_token_type();
                switch($packet_parser->get_token_type()) {
                    case '#packet-header':
                        $packet_types[] = $packet_parser->get_packet_type();
                        break;
                }
            }
        }
        $this->assertEquals(
            array(
                '#packet-header',
                '#packet-header',
                '#packet-body',
                '#packet-footer',
                '#packet-header',
                '#packet-body',
                '#packet-body',
                '#packet-body',
            ),
            $token_types
        );
        $this->assertEquals(
            array(
                '#flush',
                '#packet',
                '#pack',
            ),
            $packet_types
        );
    }

}
