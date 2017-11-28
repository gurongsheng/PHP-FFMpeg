<?php

/*
 * This file is part of PHP-FFmpeg.
 *
 * (c) Strime <contact@strime.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FFMpeg\Media;

use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
use FFMpeg\Filters\Gif\GifFilterInterface;
use FFMpeg\Filters\Gif\GifFilters;
use FFMpeg\Driver\FFMpegDriver;
use FFMpeg\FFProbe;
use FFMpeg\Exception\RuntimeException;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;

class WaveGif extends AbstractMediaType
{
    /** @var TimeCode */
    private $timecode;
    /** @var Dimension */
    private $dimension;
    /** @var integer */
    private $duration;
    /** @var Audio */
    private $audio;

    public function __construct(Audio $audio, FFMpegDriver $driver, FFProbe $ffprobe, TimeCode $timecode, Dimension $dimension, $duration = null)
    {
        parent::__construct($audio->getPathfile(), $driver, $ffprobe);
        $this->timecode = $timecode;
        $this->dimension = $dimension;
        $this->duration = $duration;
        $this->audio = $audio;
    }

    /**
     * Returns the audio related to the gif.
     *
     * @return Audio
     */
    public function getAudio()
    {
        return $this->audio;
    }

    /**
     * {@inheritdoc}
     *
     * @return GifFilters
     */
    public function filters()
    {
        return new GifFilters($this);
    }

    /**
     * {@inheritdoc}
     *
     * @return Gif
     */
    public function addFilter(GifFilterInterface $filter)
    {
        $this->filters->add($filter);

        return $this;
    }

    /**
     * @return TimeCode
     */
    public function getTimeCode()
    {
        return $this->timecode;
    }

    /**
     * @return Dimension
     */
    public function getDimension()
    {
        return $this->dimension;
    }

    /**
     * Saves the gif in the given filename.
     *
     * @param string  $pathfile
     *
     * @return Gif
     *
     * @throws RuntimeException
     */
    public function save($pathfile)
    {
        /**
         * @see http://ffmpeg.org/ffmpeg.html#Main-options
         */
        $commands = array(
            '-ss', (string)$this->timecode
        );

        if(null !== $this->duration) {
            $commands[] = '-t';
            $commands[] = (string)$this->duration;
        }
        //ffmpeg -i css.mp3 -y -ss 0 -t 5  -filter_complex "[0:a]showfreqs=mode=line:fscale=log:s=120x80,format=yuv420p[v]"  -r 15 -map "[v]" css.gif 

        $commands[] = '-i';
        $commands[] = $this->pathfile;
        $commands[] = '-filter_complex';
        $commands[] = '[0:a]showfreqs=mode=line:fscale=log:s=' . $this->dimension->getWidth() . 'x' . $this->dimension->getHeight() . ',format=yuv420p[v]';
        $commands[] = '-r';
        $commands[] = '15';
        $commands[] = '-map';
        $commands[] = '[v]';
        $commands[] = '-gifflags';
        $commands[] = '+transdiff';
        $commands[] = '-y';

        foreach ($this->filters as $filter) {
            $commands = array_merge($commands, $filter->apply($this));
        }

        $commands = array_merge($commands, array($pathfile));

        try {
            $this->driver->command($commands);
        } catch (ExecutionFailureException $e) {
            var_dump($e);
            $this->cleanupTemporaryFile($pathfile);
            throw new RuntimeException('Unable to save gif', $e->getCode(), $e);
        }

        return $this;
    }
}
