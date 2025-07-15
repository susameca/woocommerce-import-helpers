<?php
namespace Woo_Import_Helpers;

class Price {
	public static function round_up_to_ten( $roundee ) {
		return ceil( $roundee / 10 ) * 10;
	}
	
	public static function get_bnb_data_for_date( $date, $currency = 'USD', $try = 1 ) {
		global $rates;

		if ( empty( $rates ) ) {
			$rates = [];
		}

		if ( isset( $rates[ $date ][ $currency ] ) ) {
			return $rates[ $date ][ $currency ];
		}

		$rate = 1;
		$url = 'https://www.bnb.bg/Statistics/StExternalSector/StExchangeRates/StERForeignCurrencies/index.htm?downloadOper=true&group1=first&firstDays=' . date_i18n( 'd', $date ) . '&firstMonths=' . date_i18n( 'm', $date ) . '&firstYear=' . date_i18n( 'Y', $date ) . '&search=true&showChart=false&showChartButton=false&type=CSV';
		$request = wp_remote_get( $url );
		$csv_text =  wp_remote_retrieve_body( $request );
		$csv_text = str_replace('﻿Курсове на българския лев към отделни чуждестранни валути и цена на златото, валидни за ' . date_i18n( 'd.m.Y', $date ), '', $csv_text );
		$csv_text = str_replace('Забележка: Валутните курсове се определят на основание чл. 12 от Валутния закон и се използват за целите, предвидени по закон.', '', $csv_text );

		$lines = array_filter( explode( date_i18n( 'd.m.Y', $date ) . ',', $csv_text ) );
		$lines = array_reverse( $lines );

		foreach ( $lines as $line ) {
			$csv = str_getcsv( $line );
			if ( $csv[1] === $currency ) {
				$rate = $csv[3];
				break;
			}
		}

		$rates[ $date ][ $currency ] = $rate;

		if ( $rate == 1 && $try < 5 ) {
			$rate = self::get_bnb_data_for_date( strtotime( date_i18n( 'd-m-Y', $date ) . ' -1 day' ), $currency, $try + 1 );
		}

		return $rate;
	}
}