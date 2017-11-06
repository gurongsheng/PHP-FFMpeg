<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Media;

use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use FFMpeg\Filters\Frame\FrameFilterInterface;
use FFMpeg\Filters\Frame\FrameFilters;
use FFMpeg\Driver\FFMpegDriver;
use FFMpeg\FFProbe;
use FFMpeg\Exception\RuntimeException;

class AudioCover extends AbstractMediaType
{
    /** @var Video */
    private $video;

    public function __construct(Video $video, FFMpegDriver $driver, FFProbe $ffprobe)
    {
        parent::__construct($video->getPathfile(), $driver, $ffprobe);
        $this->video = $video;
    }

    /**
     * Returns the video related to the frame.
     *
     * @return Video
     */
    public function getVideo()
    {
        return $this->video;
    }

    /**
     * {@inheritdoc}
     *
     * @return FrameFilters
     */
    public function filters()
    {
        return new FrameFilters($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return Frame
     */
    public function addFilter(FrameFilterInterface $filter)
    {
        $this->filters->add($filter);

        return $this;
    }

    /**
     * Saves the frame in the given filename.
     *
     * Uses the `unaccurate method by default.`
     *
     * @param string  $pathfile
     * @param Boolean $returnBase64
     * @param Object  $dimensions
     *
     * @return Frame
     *
     * @throws RuntimeException
     */
    public function save($pathfile, $returnBase64 = false, $dimensions=null)
    {
        /**
         * might be optimized with http://ffmpeg.org/trac/ffmpeg/wiki/Seeking%20with%20FFmpeg
         * @see http://ffmpeg.org/ffmpeg.html#Main-options
         */

        $outputFormat = $returnBase64 ? "image2pipe" : "image2";
        $commands = array(
            '-y', 
            '-i', $this->pathfile,
            '-vframes', '1',
            '-f', $outputFormat
        );
        
        if ($dimensions) {
            $sres = $dimensions->getWidth() . ':' . $dimensions->getWidth() . '/a';
            $commands[] = '-vf';
            $commands[] = 'scale=' . $sres;
        }
        
        if($returnBase64) {
            array_push($commands, "-");
        }

        foreach ($this->filters as $filter) {
            $commands = array_merge($commands, $filter->apply($this));
        }

        $commands = array_merge($commands, array($pathfile));

        try {
            if(!$returnBase64) {
                $this->driver->command($commands);
                return $this;
            }
            else {
                return $this->driver->command($commands);
            }
        } catch (ExecutionFailureException $e) {
            $this->cleanupTemporaryFile($pathfile);
            throw new RuntimeException('Unable to save cover', $e->getCode(), $e);
        }
    }
}
