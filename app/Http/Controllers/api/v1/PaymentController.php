<?php
namespace App\Http\Controllers\api\v1;

use Exception;
use App\Helper\SSLCommerz;
use App\Services\PipraPay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Traits\GeneratesTransactionId;

class PaymentController extends ResponseController
{
    use GeneratesTransactionId;

    public function storePayment(Request $request)
    {
        DB::beginTransaction();

        try {
            $userId = $request->header('userID');
            $user   = $this->getUser($userId);
            if (! $user) {
                return $this->sendError('User not found', [], 404);
            }

            $cartItems = $request->cartItems ?? [];
            if (empty($cartItems)) {
                return $this->sendError('Cart is empty', [], 400);
            }

            $this->validateProducts($cartItems);

            $cartData = $this->prepareCartData($userId, $cartItems);
            DB::table('product_carts')->insert($cartData);

            $deliveryData = $this->extractDeliveryData($request);
            $cartFromDb   = DB::table('product_carts')->where('userId', $userId)->get();

            [$originalPrice, $totalPrice] = $this->calculateTotals($cartFromDb);

            if ($request->filled('coupon')) {
                $totalPrice = $this->applyCouponDiscount($request->coupon, $totalPrice);
            }

            $tranId = $this->generateUniqueTransactionId('invoices', 'tranId');

            $invoiceId = DB::table('invoices')->insertGetId([
                'total'         => $originalPrice,
                'vat'           => 0,
                'payable'       => $totalPrice,
                'cusDetails'    => json_encode($deliveryData),
                'tranId'        => $tranId,
                'paymentStatus' => 'Pending',
                'userId'        => $userId,
                'paymentMethod' => $request->paymentType ?? 'Cash on delivery',
                'status'        => 'Pending',
                'coupon'        => $request->coupon ?? null,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $this->storeDeliveryAddress($userId, $deliveryData);
            $this->storeInvoiceProducts($invoiceId, $cartData, $userId);

            DB::table('product_carts')->where('userId', $userId)->delete();
            DB::commit();

          if ($request->paymentMethod === 'ssl') {
    $paymentURL = SSLCommerz::InitiatePayment(
        $totalPrice,
        $tranId,
        $user->phone ?? $user->email
    );

    return $this->sendResponse([
        'orderId'    => $invoiceId,
        'paymentUrl' => $paymentURL,
    ], 'Payment initiated via SSLCOMMERZ');
}

// Default response for other payment methods
return $this->sendResponse([
    'orderId'    => $invoiceId,
    'paymentUrl' => null,
], 'Payment method created successfully');

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function getUser($userId)
    {
        return DB::table('users')->find($userId);
    }

    private function validateProducts(array $cartItems)
    {
        $productIds         = array_column($cartItems, 'productId');
        $existingProductIds = DB::table('products')->whereIn('id', $productIds)->pluck('id')->toArray();
        $missing            = array_diff($productIds, $existingProductIds);

        if (! empty($missing)) {
            throw new Exception('Missing products with IDs: ' . implode(', ', $missing));
        }
    }

    private function prepareCartData($userId, array $items): array
    {
        return array_map(function ($item) use ($userId) {
            return [
                'userId'          => $userId,
                'productId'       => $item['productId'],
                'quantity'        => $item['quantity'],
                'discountPercent' => $item['discountPercent'] ?? 0,
                'price'           => $item['price'],
                'size'            => $item['size'] ?? null,
                'created_at'      => now(),
                'updated_at'      => now(),
            ];
        }, $items);
    }

    private function extractDeliveryData(Request $request): array
    {
        return $request->only([
            'userId', 'name',
            'phone', 'note', 'area', 'address',
        ]);

    }

    private function calculateTotals($cartItems): array
    {
        $originalPrice = 0;
        $totalPrice    = 0;

        foreach ($cartItems as $item) {
            $originalPrice += $item->price * $item->quantity;
            $discount        = ($item->discountPercent ?? 0) * $item->price / 100;
            $discountedPrice = $item->price - $discount;
            $totalPrice += $discountedPrice * $item->quantity;
        }

        return [$originalPrice, $totalPrice];
    }

    private function applyCouponDiscount($code, $totalPrice): float
    {
        $coupon = DB::table('coupons')->where('couponCode', $code)->first();

        if (! $coupon || ($coupon->discountPercent ?? 0) <= 0) {
            return $totalPrice;
        }

        $discount = $totalPrice * ($coupon->discountPercent / 100);
        return max(0, $totalPrice - $discount);
    }

    private function storeDeliveryAddress($userId, array $data)
    {
        DB::table('addresses')->insert(array_merge($data, [
            'userId'      => $userId,
            'name'        => $data['name'],
            'phone'       => $data['phone'],
            'area'        => $data['area'],
            'note'     => $data['note'],
        ]));
    }

    private function storeInvoiceProducts($invoiceId, array $cartData, $userId)
    {
        $products = array_map(function ($product) use ($invoiceId, $userId) {
            return [
                'invoiceId'  => $invoiceId,
                'productId'  => $product['productId'],
                'userId'     => $userId,
                'salePrice'  => $product['price'],
                'quantity'   => $product['quantity'],
                'size'       => $product['size'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $cartData);

        DB::table('invoice_products')->insert($products);
    }

    public function PaymentSuccess(Request $request)
    {
        try {
            SSLCommerz::PaymentSuccess($request->query('tran_id'), $request->input('val_id'));
            $order       = DB::table('invoices')->where(['tranId' => $request->query('tran_id')])->first();
            $redirectUrl = 'https://mehranmartbd.com/payment?status=Success&orderId=' . $order->id;
            return redirect($redirectUrl);
        } catch (Exception $e) {
            return $this->sendError('Error creating payment method: ' . $e->getMessage(), [], 500);
        }
    }

    public function PaymentFail(Request $request)
    {
        try {
            SSLCommerz::PaymentFail($request->query('tran_id'));
            return redirect('https://mehranmartbd.com/payment?status=Failed');
        } catch (Exception $e) {
            return $this->sendError('Error creating payment method: ' . $e->getMessage(), [], 500);
        }
    }

    public function PaymentCancel(Request $request)
    {
        try {
            SSLCommerz::PaymentCancel($request->query('tran_id'));
            return redirect('https://mehranmartbd.com/payment?status=Canceled');
        } catch (Exception $e) {
            return $this->sendError('Error creating payment method: ' . $e->getMessage(), [], 500);
        }
    }

   public  function Piprapay(Request $request)
    {
        try {
            $pipra = new PipraPay('721720785685d416a2d75520479400991339897860685d416a2d7581567922380', 'https://sandbox.piprapay.com', 'BDT');

$response = $pipra->createCharge([
    'full_name' => 'John Doe',
    'email_mobile' => 'john@example.com',
    'amount' => 50,
    'metadata' => ['invoiceid' => 'INV-123'],
    'redirect_url' => url('/success'),
    'cancel_url' => url('/cancel'),
    'webhook_url' => url('/webhook')
]);
dd($response);
        } catch (Exception $e) {
            return $this->sendError('Error creating payment method: ' . $e->getMessage(), [], 500);
        }
    }

}
