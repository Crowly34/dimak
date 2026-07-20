<?php

namespace App\Faker;

use Faker\Provider\Base;

/**
 * FakerPHP ships no es_MX locale, so a plain es_ES generator produces Spanish
 * (often Basque or Catalan) names and Spain phone formats for what is modelled
 * as a Guadalajara repair shop. This supplies Mexican names and local mobile
 * numbers instead.
 */
class MexicanSpanishProvider extends Base
{
    /** @var list<string> */
    protected static $firstNameMale = [
        'Alejandro', 'Carlos', 'Diego', 'Eduardo', 'Emiliano', 'Fernando', 'Francisco',
        'Gerardo', 'Javier', 'Jorge', 'José', 'Juan', 'Luis', 'Miguel', 'Rafael',
        'Ricardo', 'Roberto', 'Rodrigo', 'Santiago', 'Sergio',
    ];

    /** @var list<string> */
    protected static $firstNameFemale = [
        'Adriana', 'Alejandra', 'Ana', 'Andrea', 'Carmen', 'Claudia', 'Daniela',
        'Fernanda', 'Gabriela', 'Guadalupe', 'Isabel', 'Karla', 'Laura', 'Lucía',
        'Mariana', 'Patricia', 'Regina', 'Rosa', 'Sofía', 'Verónica',
    ];

    /** @var list<string> */
    protected static $lastName = [
        'Aguilar', 'Álvarez', 'Castillo', 'Chávez', 'Cortés', 'Delgado', 'Domínguez',
        'Espinoza', 'Flores', 'Gómez', 'González', 'Guzmán', 'Hernández', 'Jiménez',
        'López', 'Márquez', 'Martínez', 'Medina', 'Mendoza', 'Morales', 'Muñoz',
        'Navarro', 'Ortega', 'Ramírez', 'Reyes', 'Rivera', 'Rodríguez', 'Rojas',
        'Ruiz', 'Salazar', 'Sánchez', 'Torres', 'Vargas', 'Vázquez', 'Velázquez',
    ];

    /**
     * Guadalajara landline (33) and the common Mexican mobile prefixes.
     *
     * @var list<string>
     */
    protected static $phoneFormats = [
        '33########',
        '331#######',
        '333#######',
        '55########',
    ];

    public function firstNameMale(): string
    {
        return $this->pick(static::$firstNameMale);
    }

    public function firstNameFemale(): string
    {
        return $this->pick(static::$firstNameFemale);
    }

    public function firstName(): string
    {
        return $this->pick(array_merge(static::$firstNameMale, static::$firstNameFemale));
    }

    public function lastName(): string
    {
        return $this->pick(static::$lastName);
    }

    /**
     * Mexicans carry both parents' surnames, which is what the shop's sheet holds.
     */
    public function name(): string
    {
        return $this->firstName().' '.$this->lastName().' '.$this->lastName();
    }

    public function phoneNumber(): string
    {
        return static::numerify($this->pick(static::$phoneFormats));
    }

    /**
     * randomElement() is typed as returning mixed; every pool here is a non-empty
     * list of strings, so the first element is a sound fallback for the narrowing.
     *
     * @param  list<string>  $pool
     */
    private function pick(array $pool): string
    {
        $value = static::randomElement($pool);

        return is_string($value) ? $value : $pool[0];
    }
}
