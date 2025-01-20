<?php

namespace WordPress\Git\Protocol\Writers;

use WordPress\ByteStream\Writer\ByteWriter;

class GitProtocolWriter {

    private $base_writer;
    private $pack_writer;
    private $packet_writer;

    public function __construct(ByteWriter $writer) {
        $this->base_writer = $writer;
        $this->packet_writer = new PacketWriter($this->base_writer);
        $this->pack_writer = new PackWriter(new ChunkedPacketWriter($this->packet_writer));
    }

    public function append_progress_chunk( $chunk ): void {
        $this->append_packet_line($chunk, "\x02");
    }

    public function append_error_chunk( $chunk ): void {
        $this->append_packet_line($chunk, "\x03");
    }

    public function append_sideband_chunk( $packet_line ): void {
        $this->append_packet_line($packet_line, "\x01");
    }

    public function append_packfile( $repository, $pack_objects ): void {
        $this->append_pack_file_header(count($pack_objects));
        foreach($pack_objects as $object) {
            $reader = $repository->read_object($object);
            $this->append_pack_object_header($reader->get_object_type_name(), $reader->get_uncompressed_size());
            while($reader->next_bytes()) {
                $this->append_pack_object_body($reader->get_bytes());
            }
            $this->flush_pack_object_body();
            $reader->close();
        }
        $this->append_packfile_checksum();
    }

    public function append_packet_line( $line, $channel_code = '' ): void {
        $this->packet_writer->append_line($line, $channel_code);
    }

    public function append_packet_lines( $lines, $channel_code = '' ): void {
        $this->packet_writer->append_lines($lines, $channel_code);
    }

    public function append_pack_file_header($number_of_objects) {
        $this->pack_writer->append_file_header($number_of_objects);
    }

    public function append_pack_object_header( $object_type, $uncompressed_size ) {
        $this->pack_writer->append_object_header($object_type, $uncompressed_size);
    }

    public function append_pack_object_body( $bytes ): void {
        $this->pack_writer->append_bytes($bytes);
    }

    public function flush_pack_object_body(): void {
        $this->pack_writer->flush_object_body();
    }

    public function append_packfile_checksum(): void {
        $this->pack_writer->append_checksum();
    }

    public function close(): void {
        $this->packet_writer->close();
    }

}
