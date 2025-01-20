<?php

namespace WordPress\Git\Protocol\Writers;

use WordPress\ByteStream\Filter\ChecksumFilter;
use WordPress\ByteStream\Filter\DeflateFilter;
use WordPress\ByteStream\Writer\ByteWriter;
use WordPress\ByteStream\WriteStream;
use WordPress\Git\GitException;
use WordPress\Git\Model\Tree;

/**
 * Writes a packfile.
 * 
 * @see https://shafiul.github.io/gitbook/7_the_packfile.html
 */
class PackWriter implements ByteWriter {

    /**
     * Write raw bytes here.
     *
     * @var ByteWriter
     */
    private $packfile;

    /**
     * Write uncompressed bytes here and they'll be deflated on the way.
     * 
     * @var WriteStream
     */
    private $deflate;

    private $checksum_appended = false;

	static public function encode_variable_length( $number ) {
		$result = '';
		do {
			$byte = $number & 0x7F;
			$number >>= 7;
			if ( $number > 0 ) {
				$byte |= 0x80;
			}
			$result .= chr( $byte );
		} while ( $number > 0 );
		return $result;
	}

	static public function encode_tree_bytes( Tree $tree ) {
		$tree_bytes = '';
		foreach ( $tree->entries as $entry ) {
			$tree_bytes .= $entry->mode . ' ' . $entry->name . "\0" . hex2bin( $entry->hash );
		}
		return $tree_bytes;
	}

    public function __construct( ByteWriter $writer ) {
        $this->packfile = new WriteStream(
            $writer,
            [
                'checksum' => new ChecksumFilter('sha1'),
            ]
        );
    }

    public function append_file_header($number_of_objects) {
        $this->packfile->append_bytes("PACK");
        $this->packfile->append_bytes(pack('N', 2));
        $this->packfile->append_bytes(pack('N', $number_of_objects));
    }

    public function append_object_header( $object_type, $uncompressed_size ) {
        $this->packfile->append_bytes("{$object_type} {$uncompressed_size}\0");
    }

    public function append_bytes( $bytes ): void {
        if(!$this->deflate) {
            $this->deflate = new WriteStream(
                $this->packfile,
                [
                    'deflate' => new DeflateFilter(ZLIB_ENCODING_DEFLATE),
                ]
            );
        }
        $this->deflate->append_bytes($bytes);
    }

    public function flush_object_body(): void {
        $this->deflate->close();
        $this->deflate = null;
    }

    public function append_checksum(): void {
        if($this->checksum_appended) {
            throw new GitException('Checksum already appended');
        }

        $hash = $this->packfile['checksum']->get_hash();
        unset($this->packfile['checksum']);
        $this->packfile->append_bytes($hash);
        $this->packfile->close();
        $this->checksum_appended = true;
    }

    public function close(): void {
        if(!$this->checksum_appended) {
            $this->append_checksum();
        }
    }

}
