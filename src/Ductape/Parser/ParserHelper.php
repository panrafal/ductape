<?php

namespace Ductape\Parser;

class ParserHelper {
    
    public static function resolveNodeValue($node, $constants = array()) {
        if ($node instanceof \PHPParser_Node_Scalar_String
                || $node instanceof \PHPParser_Node_Scalar_DNumber
                || $node instanceof \PHPParser_Node_Scalar_LNumber
        ) {
            
            return $node->value;
            
        } elseif ($node instanceof \PHPParser_Node_Scalar_DirConst) {
            
            if (!isset($constants['__DIR__'])) throw new \Exception("Constant __DIR__ is missing!");
            return $constants['__DIR__'];
            
        } elseif ($node instanceof \PHPParser_Node_Scalar_FileConst) {
            
            if (!isset($constants['__FILE__'])) throw new \Exception("Constant __FILE__ is missing!");
            return $constants['__FILE__'];
            
        } elseif ($node instanceof \PHPParser_Node_Expr_Concat) {
            
            return self::resolveNodeValue($node->left, $constants) . self::resolveNodeValue($node->right, $constants);
            
        } elseif ($node instanceof \PHPParser_Node_Expr_FuncCall) {
            
            $function = (string)$node->name;
            if (!function_exists($function)) throw new \Exception("Unknown function '$name'!");
            
            $args = array();
            foreach($node->args as $arg) {
                $args[] = self::resolveNodeValue($arg->value, $constants);
            }
            
            return call_user_func_array($function, $args);
            
        }
    }
    
}