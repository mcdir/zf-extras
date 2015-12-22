<?php

namespace ZfExtra\Console\Input;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Zend\Console\Request;

/**
 * RequestInput represents an input provided as an console request.
 *
 * @license MIT
 * @author Aleksandr Sandrovskiy <a.sandrovsky@gmail.com>
 */
class RequestInput extends ArgvInput
{

    /**
     * Constructor
     *
     * @param Request $request
     * @param InputDefinition $definition
     */
    public function __construct(Request $request, InputDefinition $definition = null)
    {
        $parameters = array(
            null,
        );

        foreach ($request->getParams() as $key => $param) {
            if (is_numeric($key)) {
                $parameters[] = $param;
            }
        }

        parent::__construct($parameters, $definition);
    }

}
