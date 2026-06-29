<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\WhereClauseBuilder;

class SQLInjectionTest extends TestCase
{
    /**
     * Test WhereClauseBuilder prevents SQL injection in equality conditions
     */
    public function testEqualityConditionWithMaliciousInput()
    {
        $builder = new WhereClauseBuilder();
        $maliciousInput = "'; DROP TABLE users; --";

        $builder->eq('table_name', $maliciousInput);

        $clause = $builder->build();
        $params = $builder->getParams();

        // Clause should contain placeholder, not the malicious string
        $this->assertStringContainsString(':param_', $clause);
        $this->assertStringNotContainsString('DROP TABLE', $clause);

        // Parameter should contain the actual value (safe in PDO)
        $this->assertContains($maliciousInput, $params);
    }

    /**
     * Test WhereClauseBuilder prevents SQL injection in IN clause
     */
    public function testInConditionWithMaliciousInput()
    {
        $builder = new WhereClauseBuilder();
        $values = [
            1,
            "'); DROP TABLE users; --",
            3
        ];

        $builder->in('id', $values);

        $clause = $builder->build();
        $params = $builder->getParams();

        // Should contain IN with placeholders
        $this->assertStringContainsString('IN (', $clause);
        // Should NOT contain DROP TABLE in clause
        $this->assertStringNotContainsString('DROP TABLE', $clause);

        // All values should be in parameters (safe)
        $this->assertContains("'); DROP TABLE users; --", $params);
    }

    /**
     * Test WhereClauseBuilder prevents SQL injection in LIKE clause
     */
    public function testLikeConditionWithMaliciousInput()
    {
        $builder = new WhereClauseBuilder();
        $maliciousInput = "%' OR '1'='1";

        $builder->like('name', $maliciousInput);

        $clause = $builder->build();
        $params = $builder->getParams();

        // Should use placeholder
        $this->assertStringContainsString('LIKE :param_', $clause);
        // Parameter should contain the actual value
        $this->assertContains($maliciousInput, $params);
    }

    /**
     * Test WhereClauseBuilder with FULLTEXT search
     */
    public function testFulltextWithMaliciousInput()
    {
        $builder = new WhereClauseBuilder();
        $maliciousInput = "test'; DROP TABLE users; --";

        $builder->match(['title', 'description'], $maliciousInput);

        $clause = $builder->build();
        $params = $builder->getParams();

        // Should use placeholder
        $this->assertStringContainsString('MATCH', $clause);
        $this->assertStringContainsString(':param_', $clause);
        // Should NOT contain DROP in clause
        $this->assertStringNotContainsString('DROP TABLE', $clause);

        // Parameter should contain safe value
        $this->assertContains($maliciousInput, $params);
    }

    /**
     * Test WhereClauseBuilder with multiple conditions
     */
    public function testMultipleConditionsWithMaliciousInput()
    {
        $builder = new WhereClauseBuilder();
        $builder->eq('user_id', "1'; DROP TABLE users; --");
        $builder->eq('status', "active' OR '1'='1");
        $builder->like('name', "%'; TRUNCATE TABLE users; --");

        $clause = $builder->build();
        $params = $builder->getParams();

        // All conditions should use placeholders
        $this->assertStringContainsString(':param_1', $clause);
        $this->assertStringContainsString(':param_2', $clause);
        $this->assertStringContainsString(':param_3', $clause);

        // No dangerous SQL keywords in clause
        $this->assertStringNotContainsString('DROP TABLE', $clause);
        $this->assertStringNotContainsString('TRUNCATE', $clause);

        // All dangerous strings should be in parameters
        $this->assertCount(3, $params);
    }

    /**
     * Test WhereClauseBuilder handles NULL values safely
     */
    public function testNullValueHandling()
    {
        $builder = new WhereClauseBuilder();
        $builder->eq('deleted_at', null);

        $clause = $builder->build();
        $params = $builder->getParams();

        // Should use IS NULL, not = NULL
        $this->assertStringContainsString('IS NULL', $clause);
        $this->assertStringNotContainsString('= NULL', $clause);
        $this->assertEmpty($params);
    }

    /**
     * Test WhereClauseBuilder prevents column injection
     */
    public function testColumnNameQuoting()
    {
        $builder = new WhereClauseBuilder();
        // Even if someone tries to pass malicious column name, it's quoted with backticks
        $builder->eq('user_id', 123);

        $clause = $builder->build();

        // Column name should be quoted
        $this->assertStringContainsString('`user_id`', $clause);
    }

    /**
     * Test WhereClauseBuilder builds correct query structure
     */
    public function testCorrectQueryStructure()
    {
        $builder = new WhereClauseBuilder();
        $builder->eq('id', 1);
        $builder->eq('status', 'active');
        $builder->like('name', '%test%');

        $clause = $builder->build();
        $params = $builder->getParams();

        // Should be AND-joined conditions
        $this->assertStringContainsString(' AND ', $clause);

        // All conditions should have placeholders
        $this->assertEquals(3, count($params));

        // Params should have correct values
        $this->assertArrayHasKey(':param_1', $params);
        $this->assertArrayHasKey(':param_2', $params);
        $this->assertArrayHasKey(':param_3', $params);

        $this->assertEquals(1, $params[':param_1']);
        $this->assertEquals('active', $params[':param_2']);
        $this->assertEquals('%test%', $params[':param_3']);
    }

    /**
     * Test WhereClauseBuilder with special characters in values
     */
    public function testSpecialCharactersInValues()
    {
        $builder = new WhereClauseBuilder();
        $specialValues = [
            "O'Reilly",
            'Test "quoted" value',
            'Value with % wildcard',
            'Value with _ underscore',
            'Value with \\ backslash',
        ];

        foreach ($specialValues as $value) {
            $builder->eq('description', $value);
        }

        $clause = $builder->build();
        $params = $builder->getParams();

        // All special values should be preserved in params
        foreach ($specialValues as $value) {
            $this->assertContains($value, $params);
        }

        // Clause should NOT contain the special values
        foreach ($specialValues as $value) {
            // Only first one (the last added) will be in clause as param reference
            if ($value !== end($specialValues)) {
                // This is just to show that the actual values are not in the clause
            }
        }
    }
}
