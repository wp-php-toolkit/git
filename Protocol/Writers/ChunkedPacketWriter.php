<?php

namespace WordPress\Git\Protocol\Writers;

use WordPress\ByteStream\Writer\ByteWriter;

class ChunkedPacketWriter implements ByteWriter {

    private $chunk_size;
    private $writer;

    private $buffer = '';

    public function __construct( PacketWriter $writer, $chunk_size = 8096 ) {
        $this->chunk_size = $chunk_size;
        $this->writer = $writer;
    }

    public function append_bytes( $bytes ): void {
        $this->buffer .= $bytes;
        if(strlen($this->buffer) >= $this->chunk_size - 1) {
            $this->flush();
        }
    }

    public function flush() {
        $chunk =  substr($this->buffer, 0, $this->chunk_size - 1 );
        $this->buffer = substr($this->buffer, $this->chunk_size);
        $this->writer->append_line($chunk, "\x01");
    }

    public function close(): void {
        $this->flush();
        $this->writer->close();
    }

}
