<?php

require __DIR__ . '/../vendor/autoload.php';

$ref = new ReflectionClass('App\Controller\MLController');

if (!$ref->hasMethod('previsaoVendasMysql')) {
    fwrite(STDERR, "FALHOU: metodo previsaoVendasMysql ausente\n");
    exit(1);
}

if (!$ref->hasMethod('previsaoProdutosMysql')) {
    fwrite(STDERR, "FALHOU: metodo previsaoProdutosMysql ausente\n");
    exit(1);
}

if (!$ref->hasMethod('previsaoAnalitica')) {
    fwrite(STDERR, "FALHOU: metodo previsaoAnalitica ausente\n");
    exit(1);
}

echo "metodo-ok\n";
