<?php

namespace NFePHP\NFSeNac;

/**
 * Class for comunications with NFSe webserver in Nacional Standard
 *
 * @category  NFePHP
 * @package   NFePHP\NFSeNac
 * @copyright NFePHP Copyright (c) 2008-2018
 * @license   http://www.gnu.org/licenses/lgpl.txt LGPLv3+
 * @license   https://opensource.org/licenses/MIT MIT
 * @license   http://www.gnu.org/licenses/gpl.txt GPLv3+
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 * @link      http://github.com/nfephp-org/sped-nfse-nacional for the canonical source repository
 */

use NFePHP\NFSeNac\Common\Tools as BaseTools;
use NFePHP\NFSeNac\RpsInterface;
use NFePHP\Common\Certificate;

class Tools extends BaseTools
{
    const ERRO_EMISSAO = 1;
    const SERVICO_NAO_CONCLUIDO = 2;
    
    public function __construct($config, Certificate $cert)
    {
        parent::__construct($config, $cert);
    }
    
    /**
     * Solicita o cancelamento de NFSe (SINCRONO)
     * @param string $id
     * @param integer $numero
     * @param integer $codigo
     * @return string
     */
    public function cancelarNfse($id, $numero, $codigo = self::ERRO_EMISSAO)
    {
        $operation = 'CancelarNfse';
        $message = "<CancelarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<Pedido xmlns=\"{$this->wsobj->msgns}\">"
            . "<InfPedidoCancelamento Id=\"$id\">"
            . "<IdentificacaoNfse>"
            . "<Numero>$numero</Numero>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<CodigoMunicipio>" . $this->config->cmun . "</CodigoMunicipio>"
            . "</IdentificacaoNfse>"
            . "<CodigoCancelamento>$codigo</CodigoCancelamento>"
            . "</InfPedidoCancelamento>"
            . "</Pedido>"
            . "</CancelarNfseEnvio>";
        $content = $this->sign($message, 'InfPedidoCancelamento', 'Id');
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta Lote RPS (SINCRONO) após envio com recepcionarLoteRps() (ASSINCRONO)
     * complemento do processo de envio assincono.
     * Que deve ser usado quando temos mais de um RPS sendo enviado
     * por vez.
     * @param string $protocolo
     * @return string
     */
    public function consultarLoteRps($protocolo)
    {
        $operation = 'ConsultarLoteRps';
        $content = "<ConsultarLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
            . "<Protocolo>$protocolo</Protocolo>"
            . "</ConsultarLoteRpsEnvio>";
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe emitidas em um periodo e por tomador (SINCRONO)
     * @param string $dini
     * @param string $dfim
     * @param string $tomadorCnpj
     * @param string $tomadorCpf
     * @param string $tomadorIM
     * @return string
     */
    public function consultarNfse($dini, $dfim, $tomadorCnpj = null, $tomadorCpf = null, $tomadorIM = null)
    {
        $operation = 'ConsultarNfse';
        $content = "<ConsultarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
            . "<PeriodoEmissao>"
            . "<DataInicial>$dini</DataInicial>"
            . "<DataFinal>$dfim</DataFinal>"
            . "</PeriodoEmissao>";
            
        if ($tomadorCnpj || $tomadorCpf) {
            $content .= "<Tomador>"
            . "<CpfCnpj>";
            if (isset($tomadorCnpj)) {
                $content .= "<Cnpj>$tomadorCnpj</Cnpj>";
            } else {
                $content .= "<Cpf>$tomadorCpf</Cpf>";
            }
            $content .= "</CpfCnpj>";
            if (isset($tomadorIM)) {
                $content .= "<InscricaoMunicipal>$tomadorIM</InscricaoMunicipal>";
            }
            $content .= "</Tomador>";
        }
            $content .= "</ConsultarNfseEnvio>";
        
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe emitidas por faixa de numeros (SINCRONO)
     * @param integer $nini
     * @param integer $nfim
     * @param integer $pagina
     * @return string
     */
    public function consultarNfsePorFaixa($nini, $nfim, $pagina = 1)
    {
        $operation = 'ConsultarNfseFaixa';
        $content = "<ConsultarNfseFaixaEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . $this->prestador
            . "<Faixa>"
            . "<NumeroNfseInicial>$nini</NumeroNfseInicial>"
            . "<NumeroNfseFinal>$nfim</NumeroNfseFinal>"
            . "</Faixa>"
            . "<Pagina>$pagina</Pagina>"
            . "</ConsultarNfseFaixaEnvio>";
        
        return $this->send($content, $operation);
    }
    
    /**
     * Consulta NFSe por RPS (SINCRONO)
     * @param integer $numero
     * @param string $serie
     * @param integer $tipo
     * @return string
     */
    public function consultarNfsePorRps($numero, $serie, $tipo)
    {
        $operation = "ConsultarNfseRps";
        $content = "<ConsultarNfseRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<IdentificacaoRps>"
            . "<Numero>$numero</Numero>"
            . "<Serie>$serie</Serie>"
            . "<Tipo>$tipo</Tipo>"
            . "</IdentificacaoRps>"
            . $this->prestador
            . "</ConsultarNfseRpsEnvio>";
        
        return $this->send($content, $operation);
    }
    
    /**
     * Envia LOTE de RPS para emissão de NFSe (ASSINCRONO)
     * @param array $arps Array contendo de 1 a 50 RPS::class
     * @param string $lote Número do lote de envio
     * @return string
     * @throws \Exception
     */
    public function recepcionarLoteRps($arps, $lote)
    {
        $operation = 'EnviarLoteRps';
        $no_of_rps_in_lot = count($arps);
        if ($no_of_rps_in_lot > 50) {
            throw new \Exception('O limite é de 50 RPS por lote enviado.');
        }
        $content = '';
        foreach ($arps as $rps) {
            $xml = $this->putPrestadorInRps($rps);
            $xmlsigned = $this->sign($xml, 'InfRps', 'Id');
            $content .= $xmlsigned;
        }
        $contentmsg = "<EnviarLoteRpsEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<LoteRps Id=\"$lote\" versao=\"{$this->wsobj->version}\">"
            . "<NumeroLote>$lote</NumeroLote>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<QuantidadeRps>$no_of_rps_in_lot</QuantidadeRps>"
            . "<ListaRps>"
            . $content
            . "</ListaRps>"
            . "</LoteRps>"
            . "</EnviarLoteRpsEnvio>";
        
        $content = $this->sign($contentmsg, 'LoteRps', 'Id');
            
        return $this->send($content, $operation);
    }
    
    /**
     * Solicita a emissão de uma NFSe de forma SINCRONA
     * @param RpsInterface $rps
     * @param string $lote Identificação do lote
     * @return string
     */
    public function gerarNfse(RpsInterface $rps, $lote)
    {
        $operation = "GerarNfse";
        
        $xmlsigned = $this->putPrestadorInRps($rps);
        $xmlsigned = $this->sign($xmlsigned, 'InfRps', 'Id');
        
        $contentmsg = "<GerarNfseEnvio xmlns=\"{$this->wsobj->msgns}\">"
            . "<LoteRps Id=\"$lote\" versao=\"{$this->wsobj->version}\">"
            . "<NumeroLote>$lote</NumeroLote>"
            . "<Cnpj>" . $this->config->cnpj . "</Cnpj>"
            . "<InscricaoMunicipal>" . $this->config->im . "</InscricaoMunicipal>"
            . "<QuantidadeRps>1</QuantidadeRps>"
            . "<ListaRps>"
            . $xmlsigned
            . "</ListaRps>"
            . "</LoteRps>"
            . "</GerarNfseEnvio>";
        $content = $this->sign($contentmsg, 'LoteRps', 'Id');
        
        return $this->send($content, $operation);
    }
}
