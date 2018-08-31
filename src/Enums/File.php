<?php

namespace Dropboxv2\Enums;

/**
 * File Enumerator
 * @package Dropboxv2
 */
abstract class File
{
    const SEARCH_MODE_FILENAME              = 'filename';
    const SEARCH_MODE_FILENAMEANDCONTENT    = 'filename_and_content';
    const SEARCH_MODE_DELETEDFILENAME       = 'deleted_filename';

    const WRITE_MODE_ADD                    = 'add';
    const WRITE_MODE_OVERWRITE              = 'overwrite';
    const WRITE_MODE_UPDATE                 = 'update';
}