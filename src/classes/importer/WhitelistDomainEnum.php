<?php
namespace RPI_ls_Newsletter\classes\importer;

enum WhitelistDomainEnum: string
{
    case UNSPLASH = 'unsplash.com';
    case MATERIALPOOL = 'material.rpi-virtuell.de';


    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}