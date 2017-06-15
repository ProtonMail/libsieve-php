libsieve-php
============

libsieve-php is a library to manage and modify sieve (RFC5228) scripts. It contains a parser for the sieve language (including extensions) and a client for the managesieve protocol. It is written entirely in PHP 5.

This project is adopted from the discontinued PHP sieve library available at https://sourceforge.net/projects/libsieve-php/.

Changes from the RFC
====================

 - The `date` and the `currentdate` both allow for `zone` parameter any string to be passed.
   This allows the user to enter zone names like `Europe/Zurich` instead of `+0100`. 
    The reason we allow this is because offsets like `+0100` don't encode information about the 
    daylight saving time, which is often needed.