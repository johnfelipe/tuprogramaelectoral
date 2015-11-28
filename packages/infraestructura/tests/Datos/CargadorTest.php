<?php

require_once __DIR__ . "/../BaseDeDatos_TestCase.php";

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\SimplifiedYamlDriver;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use TPE\Dominio\Ambito\Ambito;
use TPE\Dominio\Partido\Partido;
use TPE\Dominio\Partido\Politica;
use TPE\Infraestructura\Ambito\AmbitoBaseDeDatosRepositorio;
use TPE\Infraestructura\Datos\BaseDeDatosRepositorio;
use TPE\Infraestructura\Datos\Cargador;
use TPE\Infraestructura\Datos\LectorDeFicheros;
use TPE\Infraestructura\Partido\PartidoBaseDeDatosRepositorio;
use VSP\Dominio\Datos\DatoInicialRepositorio;


class CargadorTest extends BaseDeDatos_TestCase
{
    public function testLeeLosFicherosDeDatosYLosCargaEnLaBaseDeDatos()
    {
        $path = LectorDeFicheros::escribirFicherosDeTest([
            'ambito/sanidad/ambito.json' => '{"nombre": "Sanidad"}',
            'partido/partido-ficticio/partido.json' => '{"nombre": "Partido Ficticio", "siglas": "PF", "programa": "http://partido-ficticio.es"}',
            'ambito/sanidad/politica/partido-ficticio/politica.json' => '{"partido": "partido-ficticio", "ambito": "sanidad", "fuentes": ["http://partido-ficticio.es/programa/sanidad apartado sobre sanidad en el programa electoral del partido"]}',
            'ambito/sanidad/politica/partido-ficticio/contenido.md' => '## sanidad universal y gratuita'
        ]);

        $ambito = Ambito::crearUsandoJson('{"nombre": "Sanidad"}');
        $partido = Partido::crearUsandoJson('{"nombre": "Partido Ficticio", "siglas": "PF", "programa": "http://partido-ficticio.es"}');
        $politica = new Politica(
            $partido,
            $ambito,
            ["http://partido-ficticio.es/programa/sanidad apartado sobre sanidad en el programa electoral del partido"],
            '## sanidad universal y gratuita'
        );

        (new Cargador($this->em))->cargar(new LectorDeFicheros($path));

        $ambitos = $this->repos[self::CLASE_AMBITO]->findAll();
        $partidos = $this->repos[self::CLASE_PARTIDO]->findAll();
        $politicas = $this->repos[self::CLASE_AMBITO]->findOneBy(['id' => 'sanidad'])->getPoliticas();

        $this->assertCount(1, $ambitos);
        $this->compararAmbitos($ambito, $ambitos[0]);

        $this->assertCount(1, $partidos);
        $this->compararPartidos($partido, $partidos[0]);

        $this->assertCount(1, $politicas);
        $this->compararPoliticas($politica, $politicas[0]);
    }

    private function compararAmbitos(Ambito $esperado, Ambito $actual)
    {
        $this->assertEquals($esperado->getNombre(), $actual->getNombre());
        $this->assertEquals($esperado->getId(), $actual->getId());
        $this->assertCount(1, $actual->getPoliticas());
    }

    private function compararPartidos(Partido $esperado, Partido $actual)
    {
        $this->assertEquals($esperado->getNombre(), $actual->getNombre());
        $this->assertEquals($esperado->getId(), $actual->getId());
        $this->assertEquals($esperado->getSiglas(), $actual->getSiglas());
        $this->assertEquals($esperado->getPrograma(), $actual->getPrograma());
        $this->assertCount(1, $actual->getPoliticas());
    }

    private function compararPoliticas(Politica $esperado, Politica $actual)
    {
        $this->compararAmbitos($esperado->getAmbito(), $actual->getAmbito());
        $this->compararPartidos($esperado->getPartido(), $actual->getPartido());
        $this->assertEquals($esperado->getId(), $actual->getId());
        $this->assertEquals($esperado->getFuentes(), $actual->getFuentes());
        $this->assertEquals($esperado->getContenidoEnMarkdown(), $actual->getContenidoEnMarkdown());
        $this->assertEquals($esperado->getContenidoEnHtml(), $actual->getContenidoEnHtml());
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testLanzaExcepcionCuandoElContenidoJsonNoEsValido()
    {
        $path = LectorDeFicheros::escribirFicherosDeTest([
            'ambito/administracion-publica/ambito.json' => 'ContenidoNoValido',
        ]);

        (new Cargador($this->em))->cargar(new LectorDeFicheros($path));
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testLanzaExcepcionCuandoElJsonEstaIncompleto()
    {
        $path = LectorDeFicheros::escribirFicherosDeTest([
            'ambito/administracion-publica/ambito.json' => '{}',
        ]);

        (new Cargador($this->em))->cargar(new LectorDeFicheros($path));
    }
}
