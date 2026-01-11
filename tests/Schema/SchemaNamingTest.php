<?php

namespace Articulate\Tests\Schema;

use Articulate\Schema\SchemaNaming;
use PHPUnit\Framework\TestCase;

class SchemaNamingTest extends TestCase
{
    private SchemaNaming $schemaNaming;

    protected function setUp(): void
    {
        $this->schemaNaming = new SchemaNaming();
    }

    public function testRelationColumnWithSimpleName(): void
    {
        $this->assertSame('user_id', $this->schemaNaming->relationColumn('user'));
    }

    public function testRelationColumnWithCamelCase(): void
    {
        $this->assertSame('user_profile_id', $this->schemaNaming->relationColumn('userProfile'));
    }

    public function testRelationColumnWithPascalCase(): void
    {
        $this->assertSame('order_item_id', $this->schemaNaming->relationColumn('OrderItem'));
    }

    public function testRelationColumnWithMultipleWords(): void
    {
        $this->assertSame('shopping_cart_item_id', $this->schemaNaming->relationColumn('shoppingCartItem'));
    }

    public function testRelationColumnWithAlreadySnakeCase(): void
    {
        $this->assertSame('user_name_id', $this->schemaNaming->relationColumn('user_name'));
    }

    public function testRelationColumnWithNumbers(): void
    {
        $this->assertSame('user2_id', $this->schemaNaming->relationColumn('user2'));
    }

    public function testRelationColumnWithNumbersAndLetters(): void
    {
        $this->assertSame('test123_id', $this->schemaNaming->relationColumn('test123'));
    }

    public function testForeignKeyNameWithBasicNames(): void
    {
        $result = $this->schemaNaming->foreignKeyName('users', 'profiles', 'user_id');
        $this->assertSame('fk_users_profiles_user_id', $result);
    }

    public function testForeignKeyNameWithUnderscoreNames(): void
    {
        $result = $this->schemaNaming->foreignKeyName('user_profiles', 'user_permissions', 'profile_id');
        $this->assertSame('fk_user_profiles_user_permissions_profile_id', $result);
    }

    public function testForeignKeyNameWithLongNames(): void
    {
        $result = $this->schemaNaming->foreignKeyName('shopping_cart_items', 'product_categories', 'category_id');
        $this->assertSame('fk_shopping_cart_items_product_categories_category_id', $result);
    }

    public function testMappingTableNameWithSimpleNames(): void
    {
        $result = $this->schemaNaming->mappingTableName('users', 'roles');
        $this->assertSame('roles_users', $result); // Sorted alphabetically
    }

    public function testMappingTableNameWithCamelCase(): void
    {
        $result = $this->schemaNaming->mappingTableName('UserProfiles', 'UserPermissions');
        // Joins with underscore then applies snakeCase: UserProfiles_UserPermissions -> user_profiles__user_permissions
        $this->assertSame('user_permissions__user_profiles', $result);
    }

    public function testMappingTableNameWithDifferentOrder(): void
    {
        // Test that order doesn't matter - should be sorted
        $result1 = $this->schemaNaming->mappingTableName('posts', 'tags');
        $result2 = $this->schemaNaming->mappingTableName('tags', 'posts');

        $this->assertSame('posts_tags', $result1);
        $this->assertSame('posts_tags', $result2);
    }

    public function testMappingTableNameWithUnderscores(): void
    {
        $result = $this->schemaNaming->mappingTableName('user_profiles', 'user_permissions');
        $this->assertSame('user_permissions_user_profiles', $result);
    }

    public function testMappingTableNameWithNumbers(): void
    {
        $result = $this->schemaNaming->mappingTableName('category1', 'category2');
        $this->assertSame('category1_category2', $result);
    }

    public function testMappingTableNameWithComplexNames(): void
    {
        $result = $this->schemaNaming->mappingTableName('ShoppingCartItems', 'ProductCategories');
        // Joins with underscore then applies snakeCase: ProductCategories_ShoppingCartItems -> product_categories__shopping_cart_items
        $this->assertSame('product_categories__shopping_cart_items', $result);
    }

    public function testSnakeCasePrivateMethod(): void
    {
        $reflection = new \ReflectionClass(SchemaNaming::class);
        $method = $reflection->getMethod('snakeCase');
        $method->setAccessible(true);

        $this->assertSame('simple', $method->invoke($this->schemaNaming, 'simple'));
        $this->assertSame('camel_case', $method->invoke($this->schemaNaming, 'camelCase'));
        $this->assertSame('pascal_case', $method->invoke($this->schemaNaming, 'PascalCase'));
        $this->assertSame('complex_example', $method->invoke($this->schemaNaming, 'complexExample'));
        $this->assertSame('already_snake_case', $method->invoke($this->schemaNaming, 'already_snake_case'));
        $this->assertSame('with_numbers123', $method->invoke($this->schemaNaming, 'withNumbers123'));
    }

    public function testSnakeCaseWithConsecutiveCapitals(): void
    {
        $reflection = new \ReflectionClass(SchemaNaming::class);
        $method = $reflection->getMethod('snakeCase');
        $method->setAccessible(true);

        $this->assertSame('x_m_l_http_request', $method->invoke($this->schemaNaming, 'XMLHttpRequest'));
        $this->assertSame('user_i_d', $method->invoke($this->schemaNaming, 'userID'));
        $this->assertSame('h_t_m_l_parser', $method->invoke($this->schemaNaming, 'HTMLParser'));
    }

    public function testSnakeCaseWithEmptyString(): void
    {
        $reflection = new \ReflectionClass(SchemaNaming::class);
        $method = $reflection->getMethod('snakeCase');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($this->schemaNaming, ''));
    }

    public function testSnakeCaseWithSingleCharacter(): void
    {
        $reflection = new \ReflectionClass(SchemaNaming::class);
        $method = $reflection->getMethod('snakeCase');
        $method->setAccessible(true);

        $this->assertSame('a', $method->invoke($this->schemaNaming, 'a'));
        $this->assertSame('z', $method->invoke($this->schemaNaming, 'Z'));
    }

    public function testIntegrationRelationColumnAndForeignKey(): void
    {
        $relationColumn = $this->schemaNaming->relationColumn('userProfile');
        $this->assertSame('user_profile_id', $relationColumn);

        $fkName = $this->schemaNaming->foreignKeyName('users', 'user_profiles', $relationColumn);
        $this->assertSame('fk_users_user_profiles_user_profile_id', $fkName);
    }

    public function testIntegrationMappingTableAndRelations(): void
    {
        $mappingTable = $this->schemaNaming->mappingTableName('users', 'roles');
        $this->assertSame('roles_users', $mappingTable);

        // Test that the mapping table name is properly formatted
        $this->assertMatchesRegularExpression('/^[a-z_]+$/', $mappingTable);
        $this->assertStringNotContainsString(' ', $mappingTable);
    }
}