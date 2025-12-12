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
 * Version information.
 *
 * @package   tool_paratest
 * @copyright 2025 Andrew Hancox <andrew@opensourcelearning.co.uk>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_paratest\local;

class lib {
    public static function confighook(): void {
        global $CFG;

        $testtoken = getenv('TEST_TOKEN');
        if (empty($testtoken)) {
            return;
        }

        if (!is_numeric($testtoken)) {
            throw new \Exception('Invalid test token: ' . $testtoken);
        }

        if ($testtoken < 0 || $testtoken > $CFG->phpunit_paraunit_processes) {
            throw new \Exception('Paratest has not been initialised for the right number of processes: ' . $testtoken);
        }

        $CFG->phpunit_prefix = "phpu{$testtoken}_";
        $CFG->phpunit_dataroot .= $testtoken;
    }

    public static function init(): void {
        global $CFG;

        if (!is_numeric($CFG->phpunit_paraunit_processes) || $CFG->phpunit_paraunit_processes < 2) {
            throw new \Exception('Invalid phpunit_paraunit_processes setting: ' . $CFG->phpunit_paraunit_processes);
        }

        $procs = [];
        for ($i = 0; $i <= $CFG->phpunit_paraunit_processes; $i++) {
            $phpexec = empty($CFG->pathtophp) ? 'php' : $CFG->pathtophp;
            $pathtoinitscript = dirname(__FILE__) . "/../../../phpunit/cli/init.php";
            $procs[] = proc_open("export TEST_TOKEN=$i && $phpexec $pathtoinitscript", [STDIN, STDOUT, STDOUT], $unused);
        }

        echo $CFG->phpunit_paraunit_processes . " Threads started\n";

        $lastvalue = $CFG->phpunit_paraunit_processes;
        while (true) {
            $newvalue = self::checkallprocs($procs);

            if ($lastvalue === null || $newvalue < $lastvalue) {
                echo $newvalue . " Threads pending\n";
                $lastvalue = $newvalue;
            }

            if ($newvalue == 0) {
                break;
            }
        }

        echo "All threads completed\n";
    }

    private static function checkallprocs($procs) {
        $running = 0;
        foreach ($procs as $proc) {
            $status = proc_get_status($proc);

            if (!empty($status['running'])) {
                $running += 1;
            }
        }
        return $running;
    }

    /**
     * Split the provided config file into chunks, and run each in a separate thread.
     *
     * @param string $config The config file path, relative to $CFG->dirroot.
     */
    public static function run(string $config = 'phpunit.xml'): void {
        global $CFG;
        if (!is_numeric($CFG->phpunit_paraunit_processes) || $CFG->phpunit_paraunit_processes < 2) {
            throw new \Exception('Invalid phpunit_paraunit_processes setting: ' . $CFG->phpunit_paraunit_processes);
        }

        $originalpath = $CFG->dirroot . '/' . $config;
        if (!file_exists($originalpath)) {
            throw new \Exception('Config file not found at ' . $originalpath);
        }

        $phpunitxml = file_get_contents($originalpath);
        $xmlhead = substr(
            $phpunitxml,
            0,
            strpos($phpunitxml, '<testsuites>') + 12,
        );
        $xmlfoot = substr(
            $phpunitxml,
            strpos($phpunitxml, '</testsuites>'),
        );
        $xml = new \SimpleXMLElement($phpunitxml);
        $currentthread = 0;
        $testsuites = array_fill(0, $CFG->phpunit_paraunit_processes, []);
        foreach ($xml->testsuites->testsuite as $testsuite) {
            $testsuites[$currentthread][] = $testsuite;
            $currentthread++;
            if ($currentthread >= $CFG->phpunit_paraunit_processes) {
                $currentthread = 0;
            }
        }

        $procs = [];
        $configroot = dirname($originalpath);
        @mkdir($configroot);
        for ($i = 0; $i < $CFG->phpunit_paraunit_processes; $i++) {
            $configpath = $configroot . '/phpunit.' . $i . '.xml';
            $configfile = fopen($configpath, 'w');
            fwrite($configfile, $xmlhead);
            foreach ($testsuites[$i] as $testsuite) {
                fwrite($configfile, $testsuite->asXml() . PHP_EOL);
            }
            fwrite($configfile, $xmlfoot);
            fclose($configfile);
            $pathtophpunit = $CFG->dirroot . '/vendor/bin/phpunit';
            $procs[] = proc_open("export TEST_TOKEN={$i} && {$pathtophpunit} -c {$configpath}", [STDIN, STDOUT, STDOUT], $unused);
        }

        echo $CFG->phpunit_paraunit_processes . " Threads started\n";

        $lastvalue = $CFG->phpunit_paraunit_processes;
        while (true) {
            $newvalue = self::checkallprocs($procs);

            if ($lastvalue === null || $newvalue < $lastvalue) {
                echo $newvalue . " Threads pending\n";
                $lastvalue = $newvalue;
            }

            if ($newvalue == 0) {
                break;
            }
            sleep(1);
        }
        for ($i = 0; $i < $CFG->phpunit_paraunit_processes; $i++) {
            unlink($configroot . '/phpunit.' . $i . '.xml');
        }
    }
}
