<?php
/*
 * Copyright 2021 Cloud Creativity Limited
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace LaravelJsonApi\Eloquent\Fields;

use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use LaravelJsonApi\Contracts\Resources\Serializer\Attribute as SerializableContract;
use LaravelJsonApi\Contracts\Schema\Attribute as AttributeContract;
use LaravelJsonApi\Core\Schema\Concerns\Sortable;
use LaravelJsonApi\Core\Schema\Concerns\SparseField;
use LaravelJsonApi\Core\Support\Str;
use LaravelJsonApi\Eloquent\Contracts\EagerLoadableField;
use LaravelJsonApi\Eloquent\Contracts\Fillable;
use LaravelJsonApi\Eloquent\Contracts\Selectable;
use LaravelJsonApi\Eloquent\Contracts\Sortable as SortableContract;
use LaravelJsonApi\Eloquent\Fields\Concerns\Hideable;
use LaravelJsonApi\Eloquent\Fields\Concerns\OnRelated;
use LaravelJsonApi\Eloquent\Fields\Concerns\ReadOnly;

abstract class Attribute implements
    AttributeContract,
    EagerLoadableField,
    Fillable,
    Selectable,
    SerializableContract,
    SortableContract
{

    use Hideable;
    use OnRelated;
    use ReadOnly;
    use Sortable;
    use SparseField;

    /**
     * @var string
     */
    private string $name;

    /**
     * @var string
     */
    private string $column;

    /**
     * @var Closure|null
     */
    private ?Closure $deserializer = null;

    /**
     * @var Closure|null
     */
    private ?Closure $serializer = null;

    /**
     * @var Closure|null
     */
    private ?Closure $hydrator = null;

    /**
     * @var Closure|null
     */
    private ?Closure $extractor = null;

    /**
     * @var bool
     */
    private bool $force = false;

    /**
     * Assert that the attribute JSON value is as expected.
     *
     * @param $value
     * @return void
     */
    abstract protected function assertValue($value): void;

    /**
     * Attribute constructor.
     *
     * @param string $fieldName
     * @param string|null $column
     */
    public function __construct(string $fieldName, string $column = null)
    {
        if (empty($fieldName)) {
            throw new InvalidArgumentException('Expecting a non-empty string field name.');
        }

        $this->name = $fieldName;
        $this->column = $column ?: $this->guessColumn();
    }

    /**
     * @inheritDoc
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function serializedFieldName(): string
    {
        return $this->name();
    }

    /**
     * @return string
     */
    public function column(): string
    {
        return $this->column;
    }

    /**
     * @inheritDoc
     */
    public function columnsForField(): array
    {
        return [$this->column()];
    }

    /**
     * Customise the hydration of the model attribute.
     *
     * @param Closure $hydrator
     * @return $this
     */
    public function fillUsing(Closure $hydrator): self
    {
        $this->hydrator = $hydrator;

        return $this;
    }

    /**
     * Customise the extraction of the model attribute.
     *
     * @param Closure $extractor
     * @return $this
     */
    public function extractUsing(Closure $extractor): self
    {
        $this->extractor = $extractor;

        return $this;
    }

    /**
     * Ignore mass-assignment and always fill the attribute.
     *
     * @return $this
     */
    public function unguarded(): self
    {
        $this->force = true;

        return $this;
    }

    /**
     * Use mass-assignment rules when filling the attribute.
     *
     * @return $this
     */
    public function guarded(): self
    {
        $this->force = false;

        return $this;
    }

    /**
     * Customise deserialization of the value.
     *
     * @param Closure $deserializer
     * @return $this
     */
    public function deserializeUsing(Closure $deserializer): self
    {
        $this->deserializer = $deserializer;

        return $this;
    }

    /**
     * @param Closure $serializer
     * @return $this
     */
    public function serializeUsing(Closure $serializer): self
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function fill(Model $model, $value, array $validatedData): void
    {
        $column = $this->column();
        $value = $this->deserialize($value);

        if ($this->hydrator) {
            ($this->hydrator)($model, $column, $value, $validatedData);
            return;
        }

        $owner = $this->owner($model);

        if (false === $this->force) {
            $owner->fill([$column => $value]);
            return;
        }

        $owner->{$column} = $value;
    }

    /**
     * @inheritDoc
     */
    public function sort($query, string $direction = 'asc')
    {
        return $query->orderBy(
            $query->getModel()->qualifyColumn($this->column()),
            $direction
        );
    }

    /**
     * @inheritDoc
     */
    public function serialize(object $model)
    {
        $column = $this->column();
        $owner = $this->related ? $model->{$this->related} : $model;
        $value = $owner ? $owner->{$column} : null;

        if ($this->serializer) {
            $value = ($this->serializer)($value);
        }

        if ($this->extractor) {
            return ($this->extractor)($model, $column, $value);
        }

        return $value;
    }

    /**
     * Convert the JSON value for this field.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function deserialize($value)
    {
        $this->assertValue($value);

        if ($this->deserializer) {
            return ($this->deserializer)($value);
        }

        return $value;
    }

    /**
     * @return string
     */
    private function guessColumn(): string
    {
        return Str::underscore($this->name());
    }

}
