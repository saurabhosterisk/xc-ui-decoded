<?php

/**
 * Raised when a MaxMind DB file is truncated, corrupt, or structurally invalid.
 */
class GeoDbInvalidDatabaseException extends Exception
{
}

class_alias(
    GeoDbInvalidDatabaseException::class,
    'A5182b802F62B842DAaB96095e825470\\c3f93b919c2c302f8f010b40aeb3139F\\e5Ab5DeeFFbD227296ACB28096E5070c\\e5fEa4BB1753b166E279e9172AD7B28D'
);

// Decoder.php and Reader.php currently reference the legacy global name.
class_alias(GeoDbInvalidDatabaseException::class, 'e5fEa4BB1753b166E279e9172AD7B28D');
class_alias(GeoDbInvalidDatabaseException::class, 'InvalidDatabaseException');
