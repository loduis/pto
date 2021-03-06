<?php

class PTO_Minify
{
    public static function HTML($source, $stripslace = TRUE)
    {
        if (FALSE !== stripos($source, '<?php')) {
            // Pull out the script blocks
            preg_match_all('!<\?php.+?\?>!ism', $source, $match);
            $php_blocks = $match[0];
            $source = preg_replace(
              '!<\?php.+?\?>!ism',
              '@@@PTO:TRIM:PHP@@@',
              $source
            );
        }
        if (FALSE !== stripos($source, '<script')) {
            // Pull out the script blocks
            preg_match_all("!<script([^>]*)>.*?</script>!is", $source, $match);
            foreach ($match[1] as $i=>$property) {
                $_property = preg_replace('!( )([a-z\-:]+)=("[^"]+")!', "\n$2=$3", $property);
                if ($_property != $property) {
                    $match[0][$i] = str_replace($property, $_property, $match[0][$i]);
                }
            }
            $script_blocks = $match[0];
            $source = preg_replace(
              "!<script[^>]*>.*?</script>!is",
              '@@@PTO:TRIM:SCRIPT@@@',
              $source
            );
        }
        if (FALSE !== stripos($source, '<pre')) {
            // Pull out the pre blocks
            preg_match_all("!<pre[^>]*>.*?</pre>!is", $source, $match);
            $pre_blocks = $match[0];
            $source = preg_replace(
              "!<pre[^>]*>.*?</pre>!is",
              '@@@PTO:TRIM:PRE@@@',
              $source
            );
        }
        if (FALSE !== stripos($source, '<textarea')) {
            // Pull out the textarea blocks
            preg_match_all("!<textarea[^>]+>.*?</textarea>!is", $source, $match);
            $textarea_blocks = $match[0];
            $source = preg_replace(
              "!<textarea[^>]+>.*?</textarea>!is",
              '@@@PTO:TRIM:TEXTAREA@@@',
              $source
            );
        }
        $source = strtr($source, "\r\t\n", '   ');
        $source = preg_replace(array(
            '/( )( )+/',
            '!\s*(</?(?:body|div|form|frame|h[1-6]|head|hr|html|li|link|meta|ol|opt(?:group|ion)|p|param|' .
            't(?:able|body|head|d|h||r|foot|itle)|br|ul)\b[^>]*>)\s*!i',
            '!"\s+/>!',
            '!( )([a-z\-:_]+)=("[^"]+")!'
            ),
            array(' ', '$1', '"/>', "\n$2=$3"),
            $source
        );

        if (isset($script_blocks)) {
            // replace script blocks
            self::_replace('@@@PTO:TRIM:SCRIPT@@@', $script_blocks, $source);
            //quitando espacio al final o inicio del tag script
            $source = preg_replace('!\s+(</?(?:script)\b[^>]*>)!i', '$1', $source);
        }
        if (isset($pre_blocks)) {
            // replace pre blocks
            self::_replace('@@@PTO:TRIM:PRE@@@', $pre_blocks, $source);
        }
        if (isset ($textarea_blocks)) {
            // replace textarea blocks
            self::_replace('@@@PTO:TRIM:TEXTAREA@@@', $textarea_blocks, $source);
        }
        if (isset($php_blocks)) {
            // replace php blocks
            self::_replace('@@@PTO:TRIM:PHP@@@', $php_blocks, $source);
        }
        if ($stripslace) {
            $source = stripslashes($source);
        }

        return trim($source);

    }

    private static function _replace($search, $replace, &$buffer)
    {
        $len = strlen($search);
        $pos = 0;
        $count = count($replace);
        for ($i = 0; $i < $count; $i++) {
            // does the search-string exist in the buffer?
            if (FALSE !== ($pos = strpos($buffer, $search, $pos))) {
                // replace the search-string
                $buffer = substr_replace($buffer, $replace[$i], $pos, $len);
            } else {
                break;
            }
        }
    }


    public static function PHP($source)
    {
        // Whitespaces left and right from this signs can be ignored
        static $IW = array(
          T_CONCAT_EQUAL, // .=
          T_DOUBLE_ARROW, // =>
          T_BOOLEAN_AND, // &&
          T_BOOLEAN_OR, // ||
          T_IS_EQUAL, // ==
          T_IS_NOT_EQUAL, // != or <>
          T_IS_SMALLER_OR_EQUAL, // <=
          T_IS_GREATER_OR_EQUAL, // >=
          T_INC, // ++
          T_DEC, // --
          T_PLUS_EQUAL, // +=
          T_MINUS_EQUAL, // -=
          T_MUL_EQUAL, // *=
          T_DIV_EQUAL, // /=
          T_IS_IDENTICAL, // ===
          T_IS_NOT_IDENTICAL, // !==
          T_DOUBLE_COLON, // ::
          T_PAAMAYIM_NEKUDOTAYIM, // ::
          T_OBJECT_OPERATOR, // ->
          T_DOLLAR_OPEN_CURLY_BRACES, // ${
          T_AND_EQUAL, // &=
          T_MOD_EQUAL, // %=
          T_XOR_EQUAL, // ^=
          T_OR_EQUAL, // |=
          T_SL, // <<
          T_SR, // >>
          T_SL_EQUAL, // <<=
          T_SR_EQUAL, // >>=
        );
        //evitamos que los script en linea sean procesados
        if (FALSE !== stripos($source, '<script')) {
            // Pull out the script blocks
            preg_match_all("!<script[^>]+>.*?</script>!is", $source, $match);
            $script_blocks = $match[0];
            $source = preg_replace(
              "!<script[^>]+>.*?</script>!is",
              '@@@PTO:TRIM:SCRIPT@@@',
              $source
            );
        }
        $tokens = token_get_all($source);
        $source = '';
        $c = count($tokens);
        $iw = FALSE; // ignore whitespace
        $ls = '';    // last sign
        $ot = NULL;  // open tag
        $tn = NULL;
        $ts = NULL;
        for ($i = 0; $i < $c; $i++) {
            $token = $tokens[$i];
            if (is_array($token)) {
                list($tn, $ts) = $token; // tokens: number, string, line
                if ($tn == T_INLINE_HTML) {
                    $minify  = self::HTML($ts);
                    if (isset ($tokens[$i + 1])) {
                        list($_tn) = $tokens[$i + 1]; // tokens: number, string, line
                        if ($_tn == T_OPEN_TAG || $_tn == T_OPEN_TAG_WITH_ECHO) {
                            if ((FALSE !== ($find = strrchr($ts, ' ')) || FALSE !== ($find = strrchr($ts, "\t"))) &&
                              !trim($find)) {
                                $minify .= ' ';
                            }
                        }
                    }
                    //colocamos el espacio al principio de un html si existe cuando se cierra un tag php
                    if (isset ($tokens[$i - 1])) {
                        list($_tn) = $tokens[$i - 1]; // tokens: number, string, line
                        if ($_tn == T_CLOSE_TAG) {
                            if ($ts[0] == ' ' || $ts[0] == "\t") {
                                $minify = ' ' . $minify;
                            }
                        }
                    }
                    $source .=  $minify;
                    $iw = FALSE;
                } else {
                    if ($tn == T_OPEN_TAG) {
                        if (strpos($ts, ' ') || strpos($ts, "\n") || strpos($ts, "\t") || strpos($ts, "\r")) {
                            $ts = rtrim($ts);
                        }
                        $ts .= ' ';
                        $source .= $ts;
                        $ot = T_OPEN_TAG;
                        $iw = TRUE;
                    } elseif ($tn == T_OPEN_TAG_WITH_ECHO) {
                        $source .= $ts;
                        $ot = T_OPEN_TAG_WITH_ECHO;
                        $iw = TRUE;
                    } elseif ($tn == T_CLOSE_TAG) {
                        if ($ot == T_OPEN_TAG_WITH_ECHO) {
                            $source = rtrim($source, '; ');
                        } else {
                            $ts = ' ' . $ts;
                        }
                        $source .= rtrim($ts);
                        $ot = NULL;
                        $iw = FALSE;
                    } elseif (in_array($tn, $IW)) {
                        $source .= $ts;
                        $iw = TRUE;
                    } elseif ($tn == T_CONSTANT_ENCAPSED_STRING
                      || $tn == T_ENCAPSED_AND_WHITESPACE) {
                        if ($ts[0] == '"') {
                            $ts = addcslashes($ts, "\n\t\r");
                        }
                        $source .= $ts;
                        $iw = TRUE;
                    } elseif ($tn == T_WHITESPACE) {
                        $nt = @$tokens[$i + 1];
                        if (!$iw && (!is_string($nt) || $nt == '$') && !in_array($nt[0], $IW)) {
                            $source .= " ";
                        }
                        $iw = FALSE;
                    } elseif ($tn == T_START_HEREDOC) {
                        $source .= "<<<S\n";
                        $iw = FALSE;
                    } elseif ($tn == T_END_HEREDOC) {
                        $source .= 'S;';
                        $iw = TRUE;
                        for ($j = $i + 1; $j < $c; $j++) {
                            if (is_string($tokens[$j]) && $tokens[$j] == ';') {
                                $i = $j;
                                break;
                            } else if ($tokens[$j][0] == T_CLOSE_TAG) {
                                break;
                            }
                        }
                    } elseif ($tn == T_COMMENT || $tn == T_DOC_COMMENT) {
                        $iw = TRUE;
                    } else {
                        $source .= $ts;
                        $iw = FALSE;
                    }
                }
                $ls = '';
            } else {
                if (($token != ';' && $token != ':') || $ls != $token) {
                    $source .= $token;
                    $ls = $token;
                }
                $iw = TRUE;
            }
        }
        // remplzamos el script
        if (isset($script_blocks)) {
            // replace script blocks
            self::_replace('@@@PTO:TRIM:SCRIPT@@@', $script_blocks, $source);
        }
        return self::HTML($source, FALSE);
    }

}