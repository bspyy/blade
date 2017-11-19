<?php
class Compile
{
    private $tplDir;

    private $cacheDir;

    protected $contentTags = ['{{', '}}'];

    private $replaces    =   [
        /* foreach */
        '/@foreach *\( *((\(.*\))*.*) *\)/'     =>  '<?php foreach( $1 ): ?>',
        '/@endforeach/'                         =>  '<?php endforeach; ?>',

        /* if */
        '/@if *\( *((\(.*\))*.*) *\)/'          => '<?php if( $1 ): ?>',
        '/@elseif *\( *((\(.*\))*.*) *\)/'      => '<?php elseif( $1 ): ?>',
        '/@else\s/'                             => '<?php else: ?>',
        '/@endif/'                              => '<?php endif; ?>',
    ];

    public function __construct($tplDir,$cacheDir)
    {
        $this->tplDir   =   $tplDir;
        $this->cacheDir =   $cacheDir;

        //echo
        $pattern    =   '/'.preg_quote($this->contentTags[0]).'(.*)'.preg_quote($this->contentTags[1]).'/';
        $this->replaces[$pattern] = '<?php echo $1; ?>';
    }

    /**
     * 编译模板内容
     * @param $content
     * @param $cacheName
     * @param string $ext
     * @return string
     */
    public function compile($content,$cacheName,$ext = '.php')
    {
        $content = $this->compileExtends($content);

        $content = $this->compileInclude($content);

        $content = preg_replace(array_keys($this->replaces),array_values($this->replaces),$content);


        $compileFilename = $this->cacheDir.$cacheName.$ext;
        file_put_contents($compileFilename,$content);


        return $compileFilename;
    }

    /**
     * TODO @@parent多层上级
     * @param $content
     * @return mixed|string
     */
    private function compileExtends($content)
    {
        while (strpos($content,'@extends') !== false){
            $pattern = '/(.*)@extends *\( *[\'|\"](\w+)[\'|\"] *\)(.*)/s';
            preg_match_all($pattern,$content,$match);

            $parentTpl = $match[2][0];
            $parentFilename = $this->tplDir.'/'.$parentTpl.'.blade.php';
            $parentContent  =  file_get_contents($parentFilename);

            $tmpContent = $match[1][0].$match[3][0];//模板的其他内容

            //匹配当前内容中的@section标签，并获取内容
            $pattern = '/@section *\( *[\'|"]([a-zA-Z_]+)[\'|"] *\)(.*)(@stop|@endsection)/Us';
            preg_match_all($pattern,$tmpContent,$match2);

            if(!$match2[0]){//如果本页面没有@section 标签 直接获取父级模板内容进行处理
                $content = $parentContent;
                continue;
            }

            $iterator = new MultipleIterator(MultipleIterator::MIT_KEYS_ASSOC);
            $iterator->attachIterator(new ArrayIterator($match2[0]),'all');
            $iterator->attachIterator(new ArrayIterator($match2[1]),'key');
            $iterator->attachIterator(new ArrayIterator($match2[2]),'content');//(.*)

            foreach ($iterator as $item){
                //替换父级模板内容
                $pattern = '/@yield *\( *[\'|"]'.$item['key'].'[\'|"] *\)/';
                if(preg_match($pattern,$parentContent)){
                    $parentContent = preg_replace($pattern,$item['content'],$parentContent);
                    continue;
                }

                $pattern = '/@section *\( *[\'|"]'.$item['key'].'[\'|"] *\)(.*)(@stop|@endsection)/Us';
                if(preg_match($pattern,$parentContent,$match3)){
                    if($match3[2] == '@endsection'){
                        //如果是以@endsection 结尾，进行替换，以@stop结束不替换
                        if(strpos($item['content'],'@parent') !== false){
                            $item['all'] = str_replace('@parent',$match3[1],$item['all']);
                        }
                        $parentContent = preg_replace($pattern,$item['all'],$parentContent);
                    }
                    continue;
                }

                $parentContent .= $item['all'];
            }
            $content = $parentContent;
        }

        return $content;
    }

    /**
     * TODO 功能待完善
     * @param $content
     * @return mixed
     */
    private function compileInclude($content)
    {
        if(strpos($content,'@include') !== false){
            $pattern = '/@include *\([\'|"]?(\w*)[\'|"]?\)/';
            preg_match_all($pattern,$content,$match);

            if($match[1]){
                foreach ($match[1] as $path){
                    $filename = $this->tplDir.'/'.str_replace('.','/',$path).'.blade.php';
                    if(file_exists($filename)){
                        $replacement = file_get_contents($filename);
                        $pattern = '/@include *\( *[\'|"]?'.$path.'[\'|"]? *\)/';
                        $content = preg_replace($pattern,$replacement,$content);
                    }
                }
            }

        }
        return $content;
    }
}