# DataLayout

experimental project to simply build data access code

## main goal

With this project it should be easy to create data definitions. Additional it can create code in many formats from this definition.

For example I want to store my data in a mysql database and php to access it. It could be a pain in the ass to create the whole bunch of mysql code to create and manage tha tables, and php code to access it. 

## builder

currently is only one builder implemented but in the future I will maybe add some more to different use cases.

### php & mysql

The data is stored in a mysql database on the same or a different server. Additional a php library will be created to access and manage it. 

To use it you are required to use `lib/script/db.php` script that access the db directly. In future I plan it will be added automaticly to the build output.

You need to copy the `lib/script/config.php` in the same folder as the previous file and insert the configuration settings.

Additionaly this builder will create a `data-setup.php` file to initialize the database. It needs to executed once.

## data format

To verify your file use the `data-layout.xsd` file.

The minimum example is as following:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<DataDefinition xmlns="http://doc.mabron.de/xsd/data-layout/2019-01/data-layout.xsd"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="http://doc.mabron.de/xsd/data-layout/2019-01/data-layout.xsd">
    <Environment>
    </Environment>
    <Types>
    </Types>
</DataDefinition>
```

### Environment

In the environment you can add environment variables. These are static accessible
for all types. It is mainly used inside the querys.

The second main function of the environment is to define the some build variables.

#### PHP

| Parameter | Value | Default | Purpose |
|-|-|-|-|
| `supported` | bool | `true` | Define if this specific builder is allowed for this type definition. |
| `dbEngine` | string | `sql-mabron-db-connector` | Define which db connector should be used to access the data. Currently only `sql-mabron-db-connector` is supported. 
| `dbPrefix` | string | *empty* | Define the prefix for the db tables. |
| `classNamespace` | string | *empty* | Define the basic class namespace for the class files. This will be used as a prefix of the regular builded namespace. |
| `publicMemberAccess` | bool | `false` | Define if the class member variables has a public access scope. Normaly they will only accessible through the getters and setters. With a public member access there is no type check! |

In the result the following files will be generated:

- *db files path*`/Data/`*type name*`.php` - classes for the types
- *db files path*`/Environment.php` - class to manage the environmental variables
- *setup path*`/data-setup.php` - a single executable file to setup the database

The *db files path*  and the *db files path* will be given as start arguments to the builder.

### Types

The main part is for definiting the types. Each type consists a name, some attributes, maybe joints, links and access methods. A single type definition can inherit the definition of another type.

Each type contains a hidden attribute `id`. This will contains the unique id for each record. It is not possible set manipulate this or access it inside the querys.

Every types inherits all attributes, joints and querys from its parent. 

It is not allowed to overwrite the name of an attribute or joint and attributes cannot overwrite joints vice versa.

#### Attribute

| Argument | Type | Required | Purpose |
|-|-|-|-|
| `name` | string | `true` | the name of the type member. |
| `type` | type | `true` | The stored type of this member |
| `default` | mixed | `false` | The default value of this member. It depends on the type of the member itself. |
| `unique` | bool | `false` | If you set this to `true` this member has to be unique accross all records of this type. |
| `optional` | bool | `false` | If you set this to `true` this member can be setted to `null`. |

The arguments can cas the following types:

- `bool` - Can only contains `true` or `false`.
- `byte` - an unsigned 8-bit integer
- `short` - a signed 16-bit integer
- `int` - a signed 32-bit integer
- `long` - a signed 32-bit integer
- `sbyte` - a signed 8-bit integer
- `ushort` - an unsigned 16-bit integer
- `uint` - an unsigned 32-bit integer
- `ulong` - an unsigned 32-bit integer
- `float` - a 32-bit floating point number
- `double` - a 64-bit floating point number
- `string` - a single character sequence (string)
- `bytes` - unknown sequence of bytes (this type is not realy recommended)
- `date` - a date time value
- `json` - value that contains any json value

#### Joints

This is like an attribute but this will connect two types directly another. The type of this member is a type itself.

| `Argument` | Type | Required | Purpose |
|-|-|-|-|
| `name` | string | `true` | Name of the joint |
| `target` | string | `true` | Name of the target type |
| `required` | string | `false` | If `false` this member can be `null` and not bound to any other type |

#### Links

Enforce that a value of a member must already exists in any type.

| `Argument` | Type | Required | Purpose |
|-|-|-|-|
| `name` | string | `false` | The name of this link |
| `attribute` | string | `true` | The name of the member of the current type (only attributes) |
| `target` | string | `true` | The type that contains the target member (could be the same type) |
| `tarAttribute` | string | `true` | The name of the member that should contains this value (only attributes) |

Hint: If you create a link that points to the same type and the source and target attributes are both not optional the values has always to be equal and you cannot change these values later in mysql because of the foreign key constraint!

#### Query

Create a load or delete query. Load querys will search for all records of this type that match the condition and returns the objects. Delete querys will search for all records of this type that match the condition and delete them.

You can define that a query will only perform with the first found entry. It will returned or deleted depending on the mode.


## future plans

This is a list of possible extensions that I plan to maybe implement:

- add other builder for
    - different targets
    - different programming languages
    - different data storages
- more configuration options
- automated test cases (I have tested the current stuff by hand)
- modify query
- automated querys created by unique attributes
- add view members into the types:
    - these members generate their value from the other members
    - access like normal members in most ways
    - cannot set value because its auto-computed
    - this members are limited on the current entry scope (no linking, ...)
    - this members are ideal for: sorting, comparing, computing
- add math operator to bounds (+, -, *, /)
- add detailes filter to types, attributes, ... for
    - include or ignore for build

## community support

If you like this project and has an idea to improve it - feel free to fork and create pull requests. :D
