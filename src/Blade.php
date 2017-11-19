<?php
class Blade
{
    private $varPool    =   [];

    /**
     * @var Compile
     */
    private $compile;

    public function __construct($tplDir,$cacheDir)
    {
        $this->tplDir   =   $tplDir;
        $this->cacheDir =   $cacheDir;
        $this->compile  =   $this->getCompile();
    }

    public function getCompile()
    {
        if(!$this->compile) {
            $this->compile = new Compile($this->tplDir,$this->cacheDir);
        }

        return $this->compile;
    }


    public function assign($name,$val = '')
    {
        if(is_array($name)){
            $this->varPool = array_merge($this->varPool,$name);
        }else{
            $this->varPool[$name] = $val;
        }
    }

    public function getData($key)
    {
        return $this->varPool[$key];
    }

    private function getTpl($filename,$ext = '.blade.php')
    {
        $tplFilename = $this->tplDir.$filename.$ext;

        if(!file_exists($tplFilename)){
            throw new Exception('模板文件不存在');
        }
        $content = file_get_contents($tplFilename);
        return $content;
    }


    public function display($tplName,$var = [])
    {
        $tplContent = $this->getTpl($tplName);
        $filename = $this->compile->compile($tplContent,md5($tplName));
        if(!file_exists($filename)){
            throw new Exception('结果文件不存在');
        }

        extract(array_merge($this->varPool,$var));

        @include $filename;
    }
}