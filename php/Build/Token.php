<?php namespace Build;

class Token {
    private $content;
    private $indent;
    private $newline;
    private $frame;
    
    private function __constructor() {
        $this->content = null;
        $this->indent = 0;
        $this->newline = false;
        $this->frame = null;
    }

    public function build(string $indent = '    ', string $newLine = PHP_EOL): string {
        $result = $this->buildDetailed(0, false, [], $indent, $newLine);
        return $result[0];
    }

    private function buildDetailed(int $level, bool $pushed, array $frame,
        string $indent = '    ', string $newLine = PHP_EOL): array
    {
        $escape = function ($text, $includeLast = false) use ($frame) {
            for ($i = count($frame) - ($includeLast ? 1 : 2); $i >= 0; $i--)
                if ($frame[$i]['escape'] !== null)
                    $text = $frame[$i]['escape']($text);
            return $text;
        };

        $result = '';
        if ($this->frame !== null) {
            $newFrame = array_values($frame);
            $newFrame []= array(
                'prefix' => \str_repeat($indent, $level) . $this->frame['prefix'],
                'suffix' => $this->frame['suffix'],
                'escape' => $this->frame['escape']
            );
            $pushed = true;
            $res = $this->content->buildDetailed(0, false, $newFrame, $indent, $newLine);
            $result .= $res[0];
        }
        else if (\is_array($this->content)) {
            foreach ($this->content as $cont) {
                if ($cont == null) continue;
                $res = $cont->buildDetailed($level, $pushed, $frame, $indent, $newLine);
                $result .= $res[0];
                $level = $res[1];
                $pushed = $res[2];
            }
        }
        else {
            $output = is_string($this->content) && $this->content != '';
            if ($output && !$pushed) {
                for ($i = 0; $i<count($frame); $i++)
                    $result .= $escape($frame[$i]['prefix']);
                if ($level > 0)
                    $result .= $escape(\str_repeat($indent, $level));
                $pushed = true;
            }
            if ($output)
                $result .= $escape($this->content, true);
            if ($this->newline) {
                for ($i = count($frame) - 1; $i >= 0; $i--)
                    $result .= $escape($frame[$i]['suffix']);
                $result .= $newLine;
                $pushed = false;
            }
            $level += $this->indent;
        }
        return [ $result, $level, $pushed ];
    }

    public static function text(string $text): Token {
        $token = new self();
        $token->content = $text;
        return $token;
    }

    public static function multi(Token... $tokens) {
        $token = new self();
        $token->content = $tokens;
        return $token;
    }

    public static function array(array $tokens) {
        $token = new self();
        $flattened = array();
        array_walk_recursive($tokens, 
            function($a) use (&$flattened) { $flattened []= $a; }
        );
        $token->content = array_filter($flattened, function ($e) { 
            return $e !== null;
        });
        return $token;
    }

    public static function frame(Token $content, 
        string $prefix = '', string $suffix = '', 
        ?callable $escape = null): Token 
    {
        $token = new self();
        $token->content = $content;
        $token->frame = array(
            'prefix' => $prefix,
            'suffix' => $suffix,
            'escape' => $escape
        );
        return $token;
    }

    public static function nl(): Token {
        $token = new self();
        $token->newline = true;
        return $token;
    }

    public static function push(int $indent = 1): Token {
        $token = new self();
        $token->indent = $indent;
        return $token;
    }

    public static function pop(int $indent = 1): Token {
        $token = new self();
        $token->indent = -$indent;
        return $token;
    }

    public static function textnl(string $text): Token {
        $token = new self();
        $token->content = $text;
        $token->newline = true;
        return $token;
    }

    public static function textnlpush(string $text = '', int $indent = 1): Token {
        $token = new self();
        $token->content = $text;
        $token->newline = true;
        $token->indent = $indent;
        return $token;
    }

    public static function textnlpop(string $text = '', int $indent = 1): Token {
        $token = new self();
        $token->content = $text;
        $token->newline = true;
        $token->indent = -$indent;
        return $token;
    }
}
