<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Parallel run script.
 *
 * @package   tool_paratest
 * @copyright 2025 Catalyst IT Europe Ltd.
 * @author    Mark Johnson <mark.johnson@catalyst-eu.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$usage = <<<EOF
Run unit tests in parallel

Splits the provided phpunit.xml file into `\$CFG->phpunit_paraunit_processes` chunks, and runs each in a parallel process.
You must run admin/tool/paratest/cli/init.php first.

options:
--config    -c  The phpunit configuration file to run, relative to `\$CFG->dirroot` (default: phpunit.xml).
--help      -h  Display this message and exit.
--junit     -j  Path to write the junit.xml test results file. The output of each thread will be combined together into a single
                file for reporting. If the file already exists, the timing data will be used to help distribute testsuites evenly
                between threads.
EOF;


[$options] = cli_get_params(
    [
        'config' => 'phpunit.xml',
        'help' => false,
        'junit' => null,
    ],
    [
        'c' => 'config',
        'h' => 'help',
        'j' => 'junit',
    ],
);

if ($options['help']) {
    cli_write($usage);
    exit(0);
}

\tool_paratest\local\lib::run($options['config'], $options['junit']);
