# Meloq (beta)
Meloq is a migration generator for Laravel. It generates migration files based on model definitions, and uses PHP attributes and type hints to infer the database schema.

## Example
Model definitions:
```php
#[Table(primary: "id")]
class Book extends Model
{
    public int $id;
    #[Column(name: "Author")]
    public string $author;
    #[Column(name: "Title")]
    public string $title;
    public Status $status;
    public function authors(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }
}

enum Status
{
    case DRAFT;
    case PUBLISHED;
    case ARCHIVED;
}

class Author extends Model
{
    public string $name;
    #[Primary]
    public int $author_id;
    public function books() : \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Book::class);
    }
}
```
Migration files generated:
```php
Schema::create('authors', function (Blueprint $table) {
    $table->integer('id');
    $table->string('name');
    $table->integer('author_id')->unique()->primary()->autoIncrement()->index();
});
Schema::create('books', function (Blueprint $table) {
    $table->string('Author');
    $table->string('Title');
    $table->enum('status', ['DRAFT', 'PUBLISHED', 'ARCHIVED']);
    $table->integer('id');
    $table->primary('id');
});
```

## Setup
install the package:
```bash
composer require kogoshvili/meloq:dev-main
```
publish the config file (optional):
```bash
php artisan vendor:publish --tag=meloq-config
```

## Usage
When first time using Meloq, you need to record the model definitions, without creating the migration files.
Run the following command to generate json model definitions, that are used to generate migration files and keep track of the changes:
```bash
php artisan meloq:record
```

Run the following command to generate migration files based on the recorded model definitions:
```bash
php artisan meloq:migrate
```

### Defining Model
Meloq uses PHP attributes and type hints to infer the database schema.

Type Hints example:
```php
public int $id; => $table->integer('id');
public ?string $name; => $table->string('name')->nullable();
public int $total = 0; => $table->integer('total')->default(0);
```
Attributes example:
```php
#[Column(name: "author", type: "string", nullable: true)]
public string $author; => $table->string('author')->nullable();
```


### Attributes
```php
#[Column(name: "Author", type: "string", nullable: true)]
public string $author; => $table->string('Author')->nullable();
```

```php
#[Table(name: "books", primary: "id")]
```
- name: table name (default: plural of the model class name)
- primary: primary key column name (default: null)

```php
#[Column(name: "author", type: "string", comment: null, precision: null, scale: null, nullable: false, unique: false, primary: false, increment: false, index: false, default: null, value: null, foreignKey: null, referenceKey: null, referenceTable: null)]
```
- name: column name (default: property name)
- type: column type (default: type hint, e.g. int -> integer, string -> string)
- comment: column comment (default: null)
- precision: column precision (default: null)
- scale: column scale (default: null)
- nullable: whether the column is nullable (default: ?type hint, e.g. ?int -> true, int -> false)
- unique: whether the column is unique (default: false)
- primary: whether the column is primary key (default: false)
- increment: whether the column is auto increment (default: false)
- index: whether the column is indexed (default: false)
- default: default value (default: null)
- value: column value (default: null)
- foreignKey: foreign key column name (default: null)
- referenceKey: reference key column name (default: null)
- referenceTable: reference table name (default: null)


```php
#[Timestamp(name: "created_at", precision: 0)]
```
- name: column name (default: property name)
- precision: timestamp precision (default: 0)


```php
#[Primary(name: "author_id", type: "int", increment: true, comment: null)]
```
- name: column name (default: property name)
- type: column type (default: type hint, e.g. int -> integer, string -> string)
- increment: whether the column is auto increment (default: false)
- comment: column comment (default: null)

```php
#[Ignore]
```
- Ignore the property from the model definition.

### Relations
Meloq relies on the return type of the relation methods to infer the relation type, so return type must be explicitly defined.
E.g
```php
public function license() : \Illuminate\Database\Eloquent\Relations\HasOne
{
    return $this->hasOne(License::class);
}
public function appointments() : \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(Appointment::class);
}
```
```php
Schema::create('licenses', function (Blueprint $table) {
    $table->foreignId('client_id')->references('id')->on('drivers');
});
Schema::create('appointments', function (Blueprint $table) {
    $table->foreignId('doctor_id')->references('id')->on('doctors');
});
```

## Quirks
- Don't define id column with BelongsToMany relation, or you will get "Typed property App\Models\Book::$id must not be accessed before initialization" error. Instead, define primary key in the Table attribute, e.g.
```php
#[Table(primary: "id")]
class Book extends Model
{
    // public int $id
    public function authors(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }
}
```

## Todos
- Add support for on delete and on update actions for foreign keys.
- Add support for after and before actions for columns.
- Figure out how to fix Quirks.
