<?php

namespace Ductape\Analyzer;

/** 
 * Class for analyzing PHP scripts.
 * 
 * 
 * */
class ProcessAnalyzer {
    
    public function analyzeFile($file, $globals = array(), $loadClasses = array()) {
        if (!is_file($file)) throw new \Exception("File not found!");
        $cwd = dirname(realpath($file));
        $code = '<?php require_once("'.addslashes(realpath($file)).'"); ?>';
        return $this->analyzeCode($code, $cwd, $globals, $loadClasses);
    }
    
    public function analyzeCode($code, $cwd = null, $globals = array(), $loadClasses = array()) {
        
        $dumpFile = tempnam(sys_get_temp_dir(), 'php-builder');
        $options = array(
            'globals' => $globals,
            'dumpFile' => $dumpFile,
            'loadClasses' => $loadClasses
        );
        $env = array();
        
        // copy current environment, otherwise strange things happen...
        foreach($_SERVER as $k => $v) {
            if (!is_scalar($v)) continue;
            $env[$k] = $v;
        }
        
        $env['DUCTAPE'] = 1;
        $env['DUCTAPE_OPTIONS'] = serialize($options);
        
        $code = $this->generateCode($code);
        
        $process = new \Symfony\Component\Process\PhpProcess($code, $cwd, $env);
        $result = $process->run();
        
        if ($result) {
            throw new \Exception("Process did not finish succesfully\n\nResult:$result\n-- STDOUT -----------------------------------\n" . $process->getOutput() . "\n\n\n-- STDERR -----------------------------------\n" . $process->getErrorOutput());
        }
        
        $dump = file_get_contents($dumpFile);
        unlink($dumpFile);
        if ($dump) {
            $dump = unserialize($dump);
            $dump['success'] = true;
        } else {
            $dump = array(
                'success' => false
            );
        }
        
        $dump['output'] = $process->getOutput();
        $dump['errorOutput'] = $process->getErrorOutput();
        
        return $dump;
    }
    
    /** decorated code cannot have namespace declaration! */
    protected function generateCode($code) {
        $code = '<?php
                require_once("'.addslashes(realpath(__DIR__ . '/ProcessAnalyzerInjection.php')).'");
                \Ductape\Analyzer\ProcessAnalyzerInjection::instance()->start();
                ?>' 
                . $code;
        return $code;
    }
    

    /*
     * Creates fake globals for provided URL
     */
    public function fakeHttpGlobals($url) {
        $info = parse_url($url);
        $https = !empty($info['scheme']) && $info['scheme'] == 'https';
        $info = array_merge(array(
            'scheme' => $https ? 'https' : 'http',
            'host' => 'localhost',
            'port' => $https ? 443 : 80,
            'user' => '',
            'pass' => '',
            'path' => '/',
            'query' => '',
            'fragment' => '',
        ), $info);
        
        $portFragment = $info['port'] !== 80 && $info['port'] !== 443 ? ':' . $info['port'] : false;
        $queryFragment = $info['query'] ? '?' . $info['query'] : false;
        
        $result = array(
            '_SERVER' => array(
                'SERVER_PORT' => $info['port'],
                'HTTPS' => $https,
                'HTTP_HOST' => $info['host'],
                'SCRIPT_URI' => "{$info['scheme']}://{$info['host']}{$portFragment}{$info['path']}",
                'SCRIPT_URL' => "{$info['path']}",
                'REMOTE_ADDR' => "127.0.0.1",
                'HTTP_USER_AGENT' => "Ductape",
                'REQUEST_METHOD' => "GET",
                'REQUEST_URI' => "{$info['path']}{$queryFragment}",
                'QUERY_STRING' => $info['query'],
            )
        );
        if ($info['query']) {
            $result['_GET'] = array();
            parse_str($info['query'], $result['_GET']);
        }
        return $result;
    }
    
}
