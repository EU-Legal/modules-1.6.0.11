<?php
/**
 * EU Legal - Better security for German and EU merchants.
 *
 * @version   : 1.0.2
 * @date      : 2014 08 26
 * @author    : Markus Engel/Chris Gurk @ Onlineshop-Module.de | George June/Alexey Dermenzhy @ Silbersaiten.de
 * @copyright : 2014 Onlineshop-Module.de | 2014 Silbersaiten.de
 * @contact   : info@onlineshop-module.de | info@silbersaiten.de
 * @homepage  : www.onlineshop-module.de | www.silbersaiten.de
 * @license   : http://opensource.org/licenses/osl-3.0.php
 * @changelog : see changelog.txt
 * @compatibility : PS == 1.6.0.9
 */

class OrderDetail extends OrderDetailCore
{
	public function saveTaxCalculator(Order $order, $replace = false)
	{
		/*
		* EU-Legal
		* correct calculation of prices -> Problem with inaccuracy at high number of items 
		*/

		// Nothing to save
		if ($this->tax_calculator == null)
			return true;

		if (!($this->tax_calculator instanceOf TaxCalculator))
			return false;

		if (count($this->tax_calculator->taxes) == 0)
			return true;

		if ($order->total_products <= 0)
			return true;

		$shipping_tax_amount = 0;

		foreach ($order->getCartRules() as $cart_rule)
			if ($cart_rule['free_shipping'])
			{
				$shipping_tax_amount = $order->total_shipping_tax_excl;
				break;
			}

		$ratio = $this->unit_price_tax_excl / $order->total_products;
		$order_reduction_amount = ($order->total_discounts_tax_excl - $shipping_tax_amount) * $ratio;
		$discounted_price_tax_excl = $this->unit_price_tax_excl - $order_reduction_amount;

		$values = '';
		foreach ($this->tax_calculator->getTaxesAmount($discounted_price_tax_excl) as $id_tax => $amount)
		{
			switch (Configuration::get('PS_ROUND_TYPE'))
			{
				case Order::ROUND_ITEM:
					$unit_amount = (float)Tools::ps_round($amount, _PS_PRICE_COMPUTE_PRECISION_);
					$total_amount = $unit_amount * $this->product_quantity;
					break;
				case Order::ROUND_LINE:
					$unit_amount = $amount;
					$total_amount = Tools::ps_round($unit_amount * $this->product_quantity, _PS_PRICE_COMPUTE_PRECISION_);
					break;
				case Order::ROUND_TOTAL:
					$unit_amount = $amount;
					$total_amount = $unit_amount * $this->product_quantity;
					break;
			}

			$values .= '('.(int)$this->id.','.(int)$id_tax.','.(float)$unit_amount.','.(float)$total_amount.'),';
		}

		if ($replace)
			Db::getInstance()->execute('DELETE FROM `'._DB_PREFIX_.'order_detail_tax` WHERE id_order_detail='.(int)$this->id);

		$values = rtrim($values, ',');
		$sql = 'INSERT INTO `'._DB_PREFIX_.'order_detail_tax` (id_order_detail, id_tax, unit_amount, total_amount)
				VALUES '.$values;

		return Db::getInstance()->execute($sql);
	}
	
}