<?php

namespace app\models\exceptions;

class DB extends ExceptionBase
{
    private $errors;

    /**
     * @param array $errors
     */
    public function __construct($errors)
    {
        $this->errors = $errors;
        parent::__construct($this->__toString());
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $str = '';
        foreach ($this->errors as $errKey => $err) {
            if ($str !== '') {
                $str .= "\n";
            }
            if (is_array($err)) {
                $str .= $errKey . ': ' . implode(', ', $err);
            } else {
                $str .= $err;
            }
        }
        return $str;
    }
}
