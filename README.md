[![travis-ci](https://travis-ci.org/CORE-POS/IS4C.svg?branch=master)](https://travis-ci.org/CORE-POS/IS4C)
[![Code Climate](https://codeclimate.com/github/CORE-POS/IS4C/badges/gpa.svg)](https://codeclimate.com/github/CORE-POS/IS4C)
[![Test Coverage](https://codeclimate.com/github/CORE-POS/IS4C/badges/coverage.svg)](https://codeclimate.com/github/CORE-POS/IS4C/coverage)

CORE-POS is the point of sale oriented project under Co-operative
Operational Retail Environment (CORE). The code is based heavily 
on IS4C with a focus on greater modularity and collaboration.

CORE is primarily a web-based, PHP+MySQL application. There are
a few C# pieces most of which are Mono-compatible.

The master branch is not intended to be completely stable. 
Non-developer users would be best served tracking one of the
version branches.

### [Documentation](https://github.com/CORE-POS/IS4C/wiki)

### Quick overview
In this directory you'll find:
* common
  * Contains shared code used by both Fannie and POS
* documentation
  * Contains legacy documentation.
  * Up to date documentation can be found
    on Github's [wiki](https://github.com/CORE-POS/IS4C/wiki)
* fannie
  * Backend tools and reporting for POS data
* pos/is4c-nf
  * The actual POS
* scripts
  * A catch-all for utilities that don't fit elsewhere 
