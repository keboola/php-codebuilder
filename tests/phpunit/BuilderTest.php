<?php

declare(strict_types=1);

namespace Keboola\Code\Tests;

use DateTime;
use Keboola\Code\Builder;
use Keboola\Code\Exception\UserScriptException;
use PHPUnit\Framework\TestCase;
use stdClass;

class BuilderTest extends TestCase
{
    public function testIfEmpty(): void
    {
        $now = new DateTime();

        $previousMonth = clone $now;
        $previousMonth->modify('-30 days');

        $builder = new Builder();

        // second argument
        $params = ['time' =>
            [
                'previousStart' => 0,
            ],
        ];

        $definition =
            '{
            "function": "ifempty",
            "args": [
                {
                    "time": "previousStart"
                },
                {
                    "function": "strtotime",
                    "args": [
                        "-30 days",
                        ' . $now->getTimestamp() . '
                    ]
                }
            ]
        }';

        self::assertEquals(
            $builder->run(
                json_decode($definition),
                $params
            ),
            $previousMonth->getTimestamp()
        );

        // first argument
        $params = ['time' =>
            [
                'previousStart' => $now->getTimestamp(),
            ],
        ];

        self::assertEquals(
            $builder->run(
                json_decode($definition),
                $params
            ),
            $now->getTimestamp()
        );

        // bad argument count
        $definition =
            '{
            "function": "ifempty",
            "args": [
                {
                    "time": "previousStart"
                },
                {
                    "function": "strtotime",
                    "args": [
                        "-30 days",
                        ' . $now->getTimestamp() . '
                    ]
                },
                "third argument"
            ]
        }';

        try {
            $builder->run(
                json_decode($definition),
                $params
            );

            self::fail('Build of ifempty function should produce error');
        } catch (UserScriptException $e) {
            self::assertStringContainsString('Bad argument count for function \'ifempty\'!', $e->getMessage());
        }
    }

    public function testInvalidParams(): void
    {
        $builder = new Builder();
        $definition =
            '{
            "function": "date",
            "args": []
        }';

        try {
            $builder->run(json_decode($definition));
            self::fail('Invalid parameters must cause exception');
        } catch (UserScriptException $e) {
            self::assertStringContainsString('date() expects at least 1 parameter, 0 given', $e->getMessage());
        }
    }


    public function testInvalidParamsType(): void
    {
        $builder = new Builder();
        $builder->allowFunction('urlencode');

        $params = ['attr' =>
            [
                'placeholder' => 'value',
            ],
        ];

        $definition =
            '{
            "function": "md5",
            "args": [
                {
                    "function": "urlencode",
                    "args": [
                        {
                            "attr": {"placeholder": "placeholder"}
                        }
                    ]
                }
            ]
        }';

        try {
            $builder->run(json_decode($definition), $params);
            self::fail('Invalid parameters must cause exception');
        } catch (UserScriptException $e) {
            self::assertStringContainsString(
                'Error evaluating user function - attr \'{"placeholder":"placeholder"}\' is not a string!',
                $e->getMessage()
            );
        }
    }


    public function testEval(): void
    {
        $builder = new Builder();
        $params = ['attr' =>
            [
                'apiKey' => 'someApiKey',
                'test' => [
                    'secret' => "oh I'm Soooo Secret Look at meee",
                ],
            ],
        ];

        // md5(attr[apiKey] . attr[test.secret] . time())
        $definition =
            '{
            "function": "md5",
            "args": [
                {
                    "function": "concat",
                    "args": [
                        {
                            "attr": "apiKey"
                        },
                        {
                            "attr": "test.secret"
                        },
                        {
                            "function": "time"
                        },
                        "string"
                    ]
                }
            ]
        }';

        self::assertEquals(
            $builder->run(
                json_decode($definition),
                $params
            ),
            md5($params['attr']['apiKey'] . $params['attr']['test']['secret'] . time() . 'string')
        );

        $def2 =
            '{
            "function": "concat",
            "args": [
                {
                    "attr": "apiKey"
                },
                {
                    "attr": "test.secret"
                },
                {
                    "function": "time"
                },
                "string"
            ]
        }';

        self::assertEquals(
            $builder->run(
                json_decode($def2),
                $params
            ),
            sprintf('%s%s%s%s', $params['attr']['apiKey'], $params['attr']['test']['secret'], time(), 'string')
        );

        //"%%date(\'Y-m-d+H:i\', strtotime(attr[job.1.success]))%%"
        $def3 = '{
            "function": "date",
            "args": [
                "Y-m-d+H:i",
                {
                    "function": "strtotime",
                    "args": [{"attr": "job.1.success"}]
                }
            ]
        }';

        self::assertEquals(
            $builder->run(
                json_decode($def3),
                ['attr' => ['job' => [1 => ['success' => '2014-12-08T10:38:35+01:00']]]]
            ),
            date('Y-m-d+H:i', strtotime('2014-12-08T10:38:35+01:00'))
        );

        self::assertEquals(
            $builder->run(
                json_decode('{
                    "function": "implode",
                    "args": [
                        ".",
                        ["st", "ri", "ng"]
                    ]
                }')
            ),
            'st.ri.ng'
        );
    }

    public function testParams(): void
    {
        $builder = new Builder();
        $def = '{
            "function": "concat",
            "args": [
                {"attr": "c"},
                {"param": "a.b"},
                {"attr": "a.b"}
            ]
        }';

        self::assertEquals(
            $builder->run(
                json_decode($def),
                [
                    'attr' => [
                        'a' => [
                            'b' => 'String',
                        ],
                        'c' => 'Woah',
                    ],
                    'param' => [
                        'a' => [
                            'b' => 'Another',
                        ],
                    ],
                ]
            ),
            'WoahAnotherString'
        );
    }

    public function testNestedParams(): void
    {
        $builder = new Builder();
        $def = '{"my_prop": {
            "function": "concat",
            "args": [
                {"attr": "c"},
                "man"
            ]
        }}';

        $expected = new stdClass();
        $expected->my_prop = 'Batman';

        self::assertEquals(
            $expected,
            $builder->run(
                json_decode($def),
                [
                    'attr' => [
                        'c' => 'Bat',
                    ],
                ]
            )
        );
    }

    public function testParamsNotFound(): void
    {
        $builder = new Builder();
        $def = '{"attr": "a"}';

        $this->expectException(UserScriptException::class);
        $this->expectExceptionMessage("Error evaluating user function - attr 'a' not found!");
        $builder->run(json_decode($def), ['attr' => []]);
    }

    public function testParamsNotFoundType(): void
    {
        $builder = new Builder();
        $def = '{"data": "a"}';

        $this->expectException(UserScriptException::class);
        $this->expectExceptionMessage("Error evaluating user function - data 'a' not found!");
        $builder->run(json_decode($def), ['data' => []]);
    }

    public function testCheckConfigFail(): void
    {
        $builder = new Builder();

        $this->expectException(UserScriptException::class);
        $this->expectExceptionMessage("Illegal function 'var_dump'!");
        $builder->run(json_decode('{
            "function": "var_dump",
            "args": []
        }'));
    }

    public function testCheckConfigObfuscate(): void
    {
        $builder = new Builder();

        $this->expectException(UserScriptException::class);
        $this->expectExceptionMessage('Illegal function \'{"function":"concat","args":["di","e"]}\'!');
        $builder->run(json_decode('{
            "function": {
                "function": "concat",
                "args": ["di", "e"]
            },
            "args": []
        }'));
    }

    public function testAllowFunction(): void
    {
        $builder = new Builder();
        $builder->allowFunction('gettype')->allowFunction('intval');
        $val = $builder->run(json_decode('{
            "function": "gettype",
            "args": [{
                "function": "intval",
                "args": [{
                    "function": "concat",
                    "args": ["12",34]
                }]
            }]
        }'));
        self::assertEquals($val, 'integer');
    }

    public function testDenyFunction(): void
    {
        $builder = new Builder();
        $builder->denyFunction('md5')->denyFunction('thisDoesntExist');

        $this->expectException(UserScriptException::class);
        $this->expectExceptionMessage('llegal function \'md5\'!');
        $builder->run(json_decode('{
            "function": "md5",
            "args": ["test"]
        }'));
    }

    public function testArrayArgument(): void
    {
        $builder = new Builder();
        $def = json_decode('{
            "function": "implode",
            "args": [
                "\n",
                [
                    {
                        "authorization": "timestamp"
                    },
                    {
                        "request": "method"
                    },
                    "\n"
                ]
            ]
        }');

        $result = $builder->run($def, [
            'authorization' => [
                'timestamp' => 123,
            ],
            'request' => [
                'method' => 'GET',
            ],
        ]);
        self::assertEquals("123\nGET\n\n", $result);
    }
}
