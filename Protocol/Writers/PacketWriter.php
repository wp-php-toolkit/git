<?php

namespace WordPress\Git\Protocol\Writers;

use WordPress\ByteStream\Writer\ByteWriter;

class PacketWriter implements ByteWriter {

    private $delegate_writer;

    public function __construct(ByteWriter $delegate_writer) {
        $this->delegate_writer = $delegate_writer;
    }

    public function append_bytes($data): void {
        $this->delegate_writer->append_bytes($data);
    }

    public function close(): void { }

    public function append_lines(array $lines, $channel_code = '') {
        foreach($lines as $line) {
            $this->append_line($line, $channel_code);
        }
    }

    public function append_line($line, $channel_code = '') {
        $this->append_bytes(self::encode_packet_line($line, $channel_code));
    }

	public static function encode_packet_lines( array $payloads, $channel_code = '' ): string {
		$lines = array();
		foreach ( $payloads as $payload ) {
			$lines[] = self::encode_packet_line( $payload, $channel_code );
		}
		return implode( '', $lines );
	}

	public static function encode_packet_line( string $payload, $channel_code = '' ): string {
		if ( $payload === '0000' || $payload === '0001' || $payload === '0002' ) {
			return $channel_code . $payload;
		}

        if('' !== $channel_code) {
            $payload = $channel_code . $payload;
        }
        $length  = sprintf( '%04x', strlen( $payload ) + 4 );
        return $length . $payload;
	}

}
