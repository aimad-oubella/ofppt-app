<?php

namespace Tests\Unit;

use App\Services\Import\ValueNormalizer;
use App\Support\Text;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ValueNormalizerTest extends TestCase
{
    public function test_dates_texte_dans_plusieurs_formats(): void
    {
        $this->assertSame('2002-03-12', ValueNormalizer::date('12/03/2002'));
        $this->assertSame('2002-03-12', ValueNormalizer::date('12-03-2002'));
        $this->assertSame('2001-07-30', ValueNormalizer::date('2001-07-30'));
        $this->assertSame('1999-02-01', ValueNormalizer::date('01/02/99'));
    }

    public function test_numero_de_serie_excel(): void
    {
        // 37327 = 12 mars 2002 dans Excel
        $this->assertSame('2002-03-12', ValueNormalizer::date('37327'));
    }

    public function test_date_inexistante_ou_illisible_est_refusee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValueNormalizer::date('31/02/2000');
    }

    public function test_date_dans_le_futur_est_refusee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ValueNormalizer::date('01/01/'.((int) date('Y') + 2));
    }

    public function test_sexe(): void
    {
        $this->assertSame('M', ValueNormalizer::gender('Masculin'));
        $this->assertSame('F', ValueNormalizer::gender('féminin'));
        $this->expectException(InvalidArgumentException::class);
        ValueNormalizer::gender('inconnu');
    }

    public function test_annee_de_formation(): void
    {
        $this->assertSame(2, ValueNormalizer::integerInRange('2ème année', 1, 9, 'Année'));
        $this->expectException(InvalidArgumentException::class);
        ValueNormalizer::integerInRange('12', 1, 9, 'Année');
    }

    public function test_nettoyage_et_cles_de_comparaison(): void
    {
        $this->assertSame('Éléonore El Fassi', Text::clean("  Éléonore \u{00A0} El   Fassi "));
        $this->assertSame('numero d inscription', Text::key("Numéro d'inscription"));
        $this->assertSame('1234567', Text::clean(1234567.0));
        $this->assertNull(Text::clean('   '));
    }
}
