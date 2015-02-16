<?php

use Keboola\Code\Builder;

class BuilderTest extends \PHPUnit_Framework_TestCase
{
	public function testEval()
	{
		$builder = new Builder();
		$params = ['attr' =>
			[
				'apiKey' => "someApiKey",
				'test' => [
					'secret' => "oh I'm Soooo Secret Look at meee"
				]
			]
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

		$this->assertEquals(
			$builder->run(
				json_decode($definition),
				$params
			),
			md5($params['attr']['apiKey'] . $params['attr']['test']['secret'] . time() . "string")
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

		$this->assertEquals(
			$builder->run(
				json_decode($def2),
				$params
			),
			sprintf('%s%s%s%s', $params['attr']['apiKey'],  $params['attr']['test']['secret'],  time(),  "string")
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

		$this->assertEquals(
			$builder->run(
				json_decode($def3),
				['attr' => ['job' => [1 => ['success' => "2014-12-08T10:38:35+01:00"]]]]
			),
			date('Y-m-d+H:i', strtotime("2014-12-08T10:38:35+01:00"))
		);

		$this->assertEquals(
			$builder->run(
				json_decode('{
					"function": "implode",
					"args": [
						".",
						["st", "ri", "ng"]
					]
				}')
			),
			"st.ri.ng"
		);
	}

	public function testParams()
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

		$this->assertEquals(
			$builder->run(
				json_decode($def),
				[
					'attr' => [
						'a' => [
							'b' => "String"
						],
						'c' => "Woah"
					],
					'param' => [
						'a' => [
							'b' => "Another"
						]
					]
				]
			),
			"WoahAnotherString"
		);
	}

	/**
	 * @expectedException \Keboola\Code\Exception\UserScriptException
	 * @expectedExceptionMessage Error evaluating user function - attr 'a' not found!
	 */
	public function testParamsNotFound()
	{
		$builder = new Builder();
		$def = '{"attr": "a"}';

		$builder->run(json_decode($def), ['attr' => []]);
	}

	/**
	 * @expectedException \Keboola\Code\Exception\UserScriptException
	 * @expectedExceptionMessage Error evaluating user function - data 'a' not found!
	 */
	public function testParamsNotFoundType()
	{
		$builder = new Builder();
		$def = '{"data": "a"}';

		var_dump($builder->run(json_decode($def), ['data' => []]));
	}

	/**
	 * @expectedException \Keboola\Code\Exception\UserScriptException
	 * @expectedExceptionMessage Illegal function 'var_dump'!
	 */
	public function testCheckConfigFail()
	{
		$builder = new Builder();
		$builder->run(json_decode('{
			"function": "var_dump",
			"args": []
		}'));
	}

	/**
	 * @expectedException \Keboola\Code\Exception\UserScriptException
	 * @expectedExceptionMessage Illegal function '{"function":"concat","args":["di","e"]}'!
	 */
	public function testCheckConfigObfuscate()
	{
		$builder = new Builder();
		$builder->run(json_decode('{
			"function": {
				"function": "concat",
				"args": ["di", "e"]
			},
			"args": []
		}'));
	}

	public function testAllowFunction()
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
		$this->assertEquals($val, 'integer');
	}

	/**
	 * @expectedException \Keboola\Code\Exception\UserScriptException
	 * @expectedExceptionMessage Illegal function 'md5'!
	 */
	public function testDenyFunction()
	{
		$builder = new Builder();
		$builder->denyFunction('md5')->denyFunction('thisDoesntExist');
		$builder->run(json_decode('{
			"function": "md5",
			"args": ["test"]
		}'));
	}
}
