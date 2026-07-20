<?php

use PHPUnit\Framework\TestCase;
use App\Services\PDFService;

/**
 * formatDocumentNumber() es privado (usado internamente por generateOrder()),
 * pero concentra toda la lógica de formato de RUT/cédula/pasaporte — se prueba
 * vía Reflection para cubrir sus casos sin tener que generar un PDF completo.
 */
class PDFServiceTest extends TestCase
{
    private function formatDocumentNumber($doc)
    {
        $service = new PDFService();
        $method = new ReflectionMethod(PDFService::class, 'formatDocumentNumber');
        $method->setAccessible(true);
        return $method->invoke($service, $doc);
    }

    public function testFormatDocumentNumberVacioRetornaNA()
    {
        $this->assertEquals('N/A', $this->formatDocumentNumber(''));
    }

    public function testFormatDocumentNumberNuloRetornaNA()
    {
        $this->assertEquals('N/A', $this->formatDocumentNumber(null));
    }

    public function testFormatDocumentNumberVenezolanoSinGuion()
    {
        $this->assertEquals('V- 12.345.678', $this->formatDocumentNumber('V12345678'));
    }

    public function testFormatDocumentNumberVenezolanoConGuion()
    {
        $this->assertEquals('V- 12.345.678', $this->formatDocumentNumber('V-12345678'));
    }

    public function testFormatDocumentNumberExtranjeroMinusculaConEspacio()
    {
        $this->assertEquals('E- 12.345.678', $this->formatDocumentNumber('e 12345678'));
    }

    public function testFormatDocumentNumberRutConDigitoVerificadorNumerico()
    {
        $this->assertEquals('12.345.678-9', $this->formatDocumentNumber('12345678-9'));
    }

    public function testFormatDocumentNumberRutConDigitoVerificadorK()
    {
        $this->assertEquals('12.345.678-K', $this->formatDocumentNumber('12345678-k'));
    }

    public function testFormatDocumentNumberSoloDigitosLargoAgregaSeparadores()
    {
        $this->assertEquals('123.456.789', $this->formatDocumentNumber('123456789'));
    }

    public function testFormatDocumentNumberCortoNoSeFormatea()
    {
        // strlen <= 4: se deja tal cual, no tiene sentido separar por miles.
        $this->assertEquals('1234', $this->formatDocumentNumber('1234'));
    }

    public function testFormatDocumentNumberPasaporteAlfanumericoSeDejaTalCual()
    {
        $this->assertEquals('AB123456', $this->formatDocumentNumber('AB123456'));
    }

    public function testFormatDocumentNumberYaFormateadoConPuntosEsIdempotente()
    {
        $this->assertEquals('12.345.678', $this->formatDocumentNumber('12.345.678'));
    }

    public function testFormatDocumentNumberConEspaciosAlrededorSeLimpia()
    {
        $this->assertEquals('123.456.789', $this->formatDocumentNumber('  123456789  '));
    }

    public function testFormatDocumentNumberEmpresaJota()
    {
        $this->assertEquals('J- 12.345.678', $this->formatDocumentNumber('J-12345678'));
    }

    public function testFormatDocumentNumberExtranjeroGMinuscula()
    {
        $this->assertEquals('G- 12.345.678', $this->formatDocumentNumber('g-12345678'));
    }

    public function testFormatDocumentNumberPasaportePMayuscula()
    {
        $this->assertEquals('P- 12.345.678', $this->formatDocumentNumber('P12345678'));
    }

    public function testFormatDocumentNumberDvKMinusculaSeConvierteAMayuscula()
    {
        $this->assertEquals('1.234.567-K', $this->formatDocumentNumber('1234567-k'));
    }

    public function testFormatDocumentNumberConDotYDashNoCoincideConNingunPatron()
    {
        // "12.345.678-9" no calza con el patrón RUT (que exige solo dígitos antes
        // del guion, sin puntos) ni con el de prefijo de letra: se devuelve tal cual.
        $this->assertEquals('12.345.678-9', $this->formatDocumentNumber('12.345.678-9'));
    }

    public function testFormatDocumentNumberSoloLetrasSeDejaTalCual()
    {
        $this->assertEquals('SINDOCUMENTO', $this->formatDocumentNumber('SINDOCUMENTO'));
    }

    public function testFormatDocumentNumberCeroEsVacio()
    {
        // "0" es falsy en PHP -> empty('0') es true -> se trata igual que vacío.
        $this->assertEquals('N/A', $this->formatDocumentNumber('0'));
    }

    public function testFormatDocumentNumberVenezolanoConMultiplesEspacios()
    {
        $this->assertEquals('V- 12.345.678', $this->formatDocumentNumber('V   -   12345678'));
    }
}
