<?php

namespace Dropboxv2\Enums;

/**
 * Image Enumerator
 * @package Dropboxv2
 */
abstract class Image
{
    const IMG_FORMAT_JPEG   = 'jpeg';
    const IMG_FORMAT_PNG    = 'png';

    const IMG_SIZE_32X32    = 'w32h32';
    const IMG_SIZE_64X64    = 'w64h64';
    const IMG_SIZE_128X128  = 'w128h128';
    const IMG_SIZE_640X480  = 'w640h480';
    const IMG_SIZE_1024X768 = 'w1024h768';
}