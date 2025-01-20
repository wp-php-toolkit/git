<?php

namespace WordPress\Git\Protocol\Parser;

use WordPress\ByteStream\NotEnoughDataException;
use WordPress\Git\GitException;

class ProtocolDemultiplexer {

    protected $bytes = '';
    protected $bytes_read_so_far = 0;
    protected $bytes_already_forgotten = 0;
    protected $is_paused_at_incomplete_input = false;
    protected $expecting_more_input = true;
    protected $seen_pack_start = false;

    protected $chunk;
    protected $stream_code;

    public function next_chunk() {
        try {
            while(true) {
                if($this->is_paused_at_incomplete_input) {
                    return false;
                }

                if($this->is_finished()) {
                    return false;
                }

                $this->parse_chunk();
                return true;
            }
        } catch(NotEnoughDataException $e) {
            if(!$this->expecting_more_input) {
                throw $e;
            }
            $this->is_paused_at_incomplete_input = true;
            return false;
        }
    }

    private function parse_chunk() {
        $at = $this->bytes_read_so_far;
        if($at + 4 >= strlen($this->bytes)) {
            throw new NotEnoughDataException();
        }

        $length_hex = substr($this->bytes, $at, 4);
        $length = hexdec($length_hex);
        $at += 4;

        $stream_code = 'unknown';
        if($at + 1 < strlen($this->bytes)) {
            $potential_stream_code = ord($this->bytes[$at]);
            if(isset(self::STREAM_CODE_MAP[$potential_stream_code])) {
                $stream_code = self::STREAM_CODE_MAP[$potential_stream_code];
                // Skip past the stream_code byte
                $at += 1;
                $length -= 1;
            }
        }

        $this->stream_code = $stream_code;
        if($length_hex === '0000' || $length_hex === '0001' || $length_hex === '0002') {
            // Yield everything we've parsed to the consumer.
            $this->chunk = $length_hex;
            $this->bytes_read_so_far = $at;
            return;
        }

        if(0 === $length) {
            throw new GitException('Demultiplexer error: Received a zero-length chunk ' . $length_hex . ' at ' . $this->get_offset_in_stream());
        }
        $length -= 4;

        // Buffer the multiplexed chunk
        if($at + $length >= strlen($this->bytes)) {
            throw new NotEnoughDataException();
        }

        // Yield everything we've parsed to the consumer.
        $this->stream_code = $stream_code;
        $chunk = substr($this->bytes, $at, $length);
        if('unknown' === $stream_code) {
            // $chunk is not actually multiplexed so we need to relay
            // both the length hex and the chunk to the consumer.
            $this->chunk = $length_hex . $chunk;
        } else {
            // $chunk is multiplexed and the downstream consumer
            // only expects the wrapped data.
            $this->chunk = $chunk;
        }
        $this->bytes_already_forgotten += $at + $length - $this->bytes_read_so_far;
        $this->bytes_read_so_far = $at + $length;
    }

    private function get_offset_in_stream() {
        return $this->bytes_already_forgotten + $this->bytes_read_so_far;
    }

    public function append_bytes($bytes) {
        $this->bytes .= $bytes;
        $this->bytes = substr($this->bytes, $this->bytes_read_so_far);
        $this->bytes_read_so_far = 0;
        $this->is_paused_at_incomplete_input = false;
    }

    public function get_stream_code() {
        return $this->stream_code;
    }

    public function get_chunk() {
        return $this->chunk;
    }

	public function is_paused_at_incomplete_input(): bool {
		return $this->is_paused_at_incomplete_input;
	}

    public function is_finished(): bool {
        return !$this->expecting_more_input || strlen($this->bytes) === 0;
    }

	/**
	 * Indicates that all the multiplexed bytes have been provided.
	 *
	 * After calling this method, the processor will emit errors where
	 * previously it would have entered the STATE_INCOMPLETE_INPUT state.
	 */
	public function input_finished() {
		$this->expecting_more_input = false;
	}

    const STREAM_CODE_SIDE_BAND = 'side_band';
    const STREAM_CODE_PROGRESS = 'progress';
    const STREAM_CODE_FATAL = 'fatal';
    const STREAM_CODE_UNKNOWN = 'unknown';

    const STREAM_CODE_MAP = [
        0x01 => self::STREAM_CODE_SIDE_BAND,
        0x02 => self::STREAM_CODE_PROGRESS,
        0x03 => self::STREAM_CODE_FATAL,
    ];
}
