<?php

namespace Tests\Integration\Schema\Directives;

use Tests\TestCase;
use Tests\Utils\ClassWithStaticProp;

class StaticDirectiveTest extends TestCase
{
    /** @test */
    public function it_can_get_a_static_property()
    {
        $this->mockResolver(function ($root, array $args): ClassWithStaticProp {
            return new ClassWithStaticProp();
        });

        $this->schema = "
            type ClassWithStaticProp {
                name: String! @static
            }
            type Query {
                classWithStaticProp: ClassWithStaticProp! @mock
            }
        ";

        $classWithStaticPropQuery = $this->query('classWithStaticProp', [], [ 'name' ]);

        $this->assertEquals(
            ClassWithStaticProp::$name,
            $classWithStaticPropQuery->result('name')
        );
    }
}
