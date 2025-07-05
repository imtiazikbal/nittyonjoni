<?php

namespace App\Http\Controllers\api\v1;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class OrderController extends ResponseController
{
    // getOrder 

    public function getOrder(Request $request)
    {
        try{
        //     $orders = DB::table('invoices')
        // ->leftJoin('users', 'invoices.userId', '=', 'users.id')
        // ->leftJoin('coupons', 'invoices.coupon', '=', 'coupons.couponCode')
        // ->leftJoin('invoice_products', 'invoices.id', '=', 'invoice_products.invoiceId')
        // ->select(
        //     'invoices.*',
        //     DB::raw('COUNT(invoice_products.id) as totalItems'), // Count items directly
        //     DB::raw("CONCAT(users.firstName, ' ', users.lastName) as customerName"),
        //     'invoices.created_at as orderDate',
        //     'invoices.payable as totalAmount',
        //     'invoices.status as status',
        //     'coupons.couponCode as appliedCoupon',
        //     'coupons.discountPercent as couponDiscountPercent'
        // )
        // ->groupBy('invoices.id') // Group by the invoice ID
        // ->get();

        $orders = DB::table('invoices')
    ->leftJoin('users', 'invoices.userId', '=', 'users.id')
    ->leftJoin('coupons', 'invoices.coupon', '=', 'coupons.couponCode')
    ->leftJoin('invoice_products', 'invoices.id', '=', 'invoice_products.invoiceId')
    ->select(
        'invoices.id',
        'invoices.userId',
        'invoices.coupon',
'invoices.payable as totalAmount',
        'invoices.status',
    DB::raw('invoices.created_at as orderDate'),
        DB::raw('COUNT(invoice_products.id) as totalItems'),
        DB::raw("CONCAT(users.firstName, ' ', users.lastName) as customerName"),
        'coupons.couponCode as appliedCoupon',
        'coupons.discountPercent as couponDiscountPercent'
    )
    ->groupBy(
        'invoices.id',
        'invoices.userId',
        'invoices.coupon',
        'invoices.payable',
        'invoices.status',
        'invoices.created_at',
        'users.firstName',
        'users.lastName',
        'coupons.couponCode',
        'coupons.discountPercent'
    )
    ->get();

    


        $ordersModified = $orders->map(function ($order) {
            return [
                'id' => (string) $order->id,
                'customerName' => (string) $order->customerName,
                'orderDate' => (string) $order->orderDate,
                'totalAmount' => (string) $order->totalAmount,
                'appliedCoupon' => (string) $order->appliedCoupon,
                'couponDiscountPercent' => (string) $order->couponDiscountPercent,
                'totalItems' => (string) $order->totalItems,
                'status' => (string) $order->status,
            ];
        });


        $totalPending = DB::table('invoices')->where('status', 'pending')->count();
        $totalDelivered = DB::table('invoices')->where('status', 'delivered')->count();
        $totalCancelled = DB::table('invoices')->where('status', 'cancelled')->count();
        $totalReturned = DB::table('invoices')->where('status', 'returned')->count();

        $reponse = [
            'totalPending' => $totalPending,
            'totalDelivered' => $totalDelivered,
            'totalCancelled' => $totalCancelled,
            'totalReturned' => $totalReturned,
            'orders' => $ordersModified,
        ];

        
        return $this->sendResponse($reponse, 'Orders retrieved successfully.');
        }catch(Exception $e){
            return $this->sendError("error", $e->getMessage());
        }
    }



    // getSingleOrder 

    public function getSingleOrder(Request $request, $id){

                    try{
                        $order = DB::table('invoices')
    ->leftJoin('users', 'invoices.userId', '=', 'users.id')
    ->leftJoin('coupons', 'invoices.coupon', '=', 'coupons.couponCode')
    ->leftJoin('invoice_products', 'invoices.id', '=', 'invoice_products.invoiceId')
    ->leftJoin('delivery_addresses', 'invoices.id', '=', 'delivery_addresses.invoiceId')
    ->select(
        'invoices.id as id',
        DB::raw('COUNT(invoice_products.id) as totalItems'),
        DB::raw("CONCAT(users.firstName, ' ', users.lastName) as customerName"),
        DB::raw('invoices.created_at as orderDate'),
        DB::raw('invoices.payable as totalAmount'),
        'invoices.status',
        'coupons.couponCode as appliedCoupon',
        'coupons.discountPercent as couponDiscountPercent'
    )
    ->where('invoices.id', $id)
    ->groupBy(
        'invoices.id',
        'invoices.created_at',
        'invoices.payable',
        'invoices.status',
        'coupons.couponCode',
        'coupons.discountPercent',
        'users.firstName',
        'users.lastName'
    )
    ->first();

                        if (!$order) {
                            return $this->sendError('Order not found.');
                        } 
                
                            $products = DB::table('invoice_products')
                            ->join('products', 'invoice_products.productId', '=', 'products.id')
                            ->select(
                                'products.id as id',
                                'products.title',
                                DB::raw('IFNULL(products.price - (products.price * (products.discountPercent / 100)), products.price) as unitPrice'), // Discounted price
                                'products.productQuantity',
                                DB::raw('(IFNULL(products.price - (products.price * (products.discountPercent / 100)), products.price) * products.productQuantity) as totalPrice'),
                                'invoice_products.size'
                            )
                            ->where('invoice_products.invoiceId', $id)
                            ->get();
                
                            $productsModified = $products->map(function ($product) {
                                return [
                                    'id' => (string) $product->id,
                                    'title' => (string) $product->title,
                                    'unitPrice' => (int) $product->unitPrice,
                                    'quantity' => (int) $product->productQuantity,
                                    'totalPrice' => (int) $product->totalPrice,
                                    'size' => (string) $product->size
                                ];
                            });
                
                            $deliveryAddress = DB::table('delivery_addresses')
                            ->where('invoiceId', $id)
                            ->select(
                                'email',
                                'phone',
                                'firstName',
                                'lastName',
                                'country',
                                'city',
                                'address',
                                'postCode'
                            )
                            ->first();
                
                            $reponse = [
                                'id' => (string) $order->id,
                                'orderDate' => $order->orderDate,
                                'customerName'=> $order->customerName,
                                'totalAmount' => $order->totalAmount,
                                'appliedCoupon' => $order->appliedCoupon,
                                'couponDiscountPercent' => $order->couponDiscountPercent,
                                'totalItems' => $order->totalItems,
                                'status' => $order->status,
                                'deliveryAddress' => $deliveryAddress,
                                'products' => $productsModified,
                            ];
                        
                            return $this->sendResponse($reponse, 'Order retrieved successfully.');
                    }catch(Exception $e){
                        return $this->sendError($e->getMessage());
                    }
                }

                // statusUpdate 

                public function statusUpdate(Request $request, $id){
                   try{
                    $findOrder = DB::table('invoices')->where('id', $id)->first();
                    if(!$findOrder){
                        return $this->sendError('Order not Found',404);
                    }

                    // Update the status
                    $order = DB::table('invoices')->where('id',$id)->update([
                        'status'=> $request->status,
                    ]);

                    return $this->sendResponse('Order status successfully update','Order status successfully update');    
                   }catch(Exception $e){
                    return $this->sendError($e->getMessage(),500);
                   }

                }

                // 
                public function getOrderListByToken(Request $request)
{
    try {
        // Get the authenticated user based on the token
        $userId = $request->headers->get('userID');
        
        // If no user is authenticated, return error
        $findUser = DB::table('users')->where('id', $userId)->first();
        if (!$findUser) {
            return $this->sendError('Unauthorized access. Please login.');
        }
       
        

        // Fetch orders for the authenticated user, grouped by status
        $orders = DB::table('invoices')
            ->leftJoin('users', 'invoices.userId', '=', 'users.id')
            ->leftJoin('coupons', 'invoices.coupon', '=', 'coupons.couponCode')
            ->leftJoin('invoice_products', 'invoices.id', '=', 'invoice_products.invoiceId')
            ->select(
                'invoices.id as id',
                DB::raw('COUNT(invoice_products.id) as totalItems'),
                DB::raw("CONCAT(users.firstName, ' ', users.lastName) as customerName"),
                'invoices.created_at as orderDate',
                'invoices.payable as totalAmount',
                'invoices.status as status',
                'coupons.couponCode as appliedCoupon',
                'coupons.discountPercent as couponDiscountPercent'
            )
            ->where('invoices.userId', $userId)
->groupBy(
    'invoices.id',
    'users.firstName',
    'users.lastName',
    'invoices.created_at',
    'invoices.payable',
    'invoices.status',
    'coupons.couponCode',
    'coupons.discountPercent'
)
->orderBy('invoices.created_at', 'desc')

            ->get(); // Fetch the orders

      
        // Calculate the counts for each order status
        $totalPending = $orders->where('status', 'Pending')->count();
        $totalDelivered = $orders->where('status', 'Delivered')->count();
        $totalCancelled = $orders->where('status', 'Cancelled')->count();
        $totalReturned = $orders->where('status', 'Returned')->count();

        // Fetch order details
        $ordersModified = $orders->map(function ($order) {
            return [
                'id' => (string) $order->id,
                'customerName' => $order->customerName,
                'orderDate' => $order->orderDate,
                'totalAmount' => (float) $order->totalAmount,
                'totalItems' => (int) $order->totalItems,
                'status' => $order->status,
                'appliedCoupon' => $order->appliedCoupon,
                'couponDiscountPercent' => (float) $order->couponDiscountPercent,
            ];
        });

        // Response data structure
        $response = [
           
                'totalPending' => $totalPending ?? 0,
                'totalDelivered' => $totalDelivered ?? 0,
                'totalCancelled' => $totalCancelled ?? 0,
                'totalReturned' => $totalReturned ?? 0,
                'orders' => $ordersModified ?? [],
        
        ];

        // Return the response with the order list and counts
        return $this->sendResponse($response, 'Orders retrieved successfully.');

    } catch (Exception $e) {
        // Catch any exceptions and return an error response
        return $this->sendError($e->getMessage());
    }
}

// orderTracking by orderId or phone number 
public function orderTracking(Request $request, $id)
{
    try {
        $user = DB::table('users')->where('phone', $id)->first();

        
            $sliceOrderId =  substr($id, 4);
            // No user found by phone, treat input as order ID
            $orderQuery = DB::table('invoices')
                ->where('invoices.id', $sliceOrderId)
                ->leftJoin('users', 'invoices.userId', '=', 'users.id')
                ->leftJoin('invoice_products', 'invoices.id', '=', 'invoice_products.invoiceId')
                ->leftJoin('products', 'invoice_products.productId', '=', 'products.id')
                ->leftJoin('addresses', 'invoices.userId', '=', 'addresses.user_id')
                ->select(
                    'invoices.id as orderId',
                    'invoices.status',
                    'invoices.created_at as date',
                    DB::raw("CONCAT(users.firstName, ' ', users.lastName) as name"),
                    'users.phone',
                    'addresses.address',
                                        'invoices.cusDetails as cusDetails',

                    DB::raw("GROUP_CONCAT(CONCAT(products.title, ' - ', invoice_products.size) SEPARATOR ', ') as items")
                )
                ->groupBy(
                    'invoices.id', 'invoices.status', 'invoices.created_at',
                    'users.firstName', 'users.lastName', 'users.phone', 'addresses.address'
                )
                ->first();
        

        if (!$orderQuery) {
            return response()->json([
                'success' => true,
                'data' => null
            ]);
        }

        $response = [
            'id' => 'MMBD' . $orderQuery->orderId,
            'name' => $orderQuery->name,
            'phone' => $orderQuery->phone,
            'status' => $orderQuery->status,
            'date' => date('Y-m-d', strtotime($orderQuery->date)),
            'items' => array_map('trim', explode(',', $orderQuery->items ?? '')),
            'address' => json_decode($orderQuery->cusDetails) ?? ''
        ];

        return response()->json([
            'success' => true,
            'data' => $response
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Something went wrong',
            'error' => $e->getMessage()
        ], 500);
    }
}




}
