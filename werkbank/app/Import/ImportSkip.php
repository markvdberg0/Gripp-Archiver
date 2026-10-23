<?php

namespace App\Import;

use RuntimeException;

/** Gooi in Mapper::map() om een record bewust over te slaan, met een reden in het rapport. */
final class ImportSkip extends RuntimeException {}
