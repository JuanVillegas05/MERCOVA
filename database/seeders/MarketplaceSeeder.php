<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $commission = new CommissionCalculator;

        $categories = [
            ['name' => 'Tecnología', 'slug' => 'tecnologia', 'description' => 'Dispositivos y accesorios digitales', 'icon' => 'smartphone'],
            ['name' => 'Hogar', 'slug' => 'hogar', 'description' => 'Muebles y productos para el hogar', 'icon' => 'home'],
            ['name' => 'Vehículos', 'slug' => 'vehiculos', 'description' => 'Autos, bicicletas y transporte', 'icon' => 'car'],
            ['name' => 'Moda', 'slug' => 'moda', 'description' => 'Ropa, calzado y accesorios', 'icon' => 'shirt'],
            ['name' => 'Deportes', 'slug' => 'deportes', 'description' => 'Equipamiento deportivo y aire libre', 'icon' => 'dumbbell'],
            ['name' => 'Servicios', 'slug' => 'servicios', 'description' => 'Servicios profesionales y de cuidado', 'icon' => 'briefcase'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@mercova.test'],
            [
                'name' => 'Administrador Mercova',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone' => '+573001234567',
                'bio' => 'Administrador principal del marketplace.',
                'is_verified' => true,
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin2@mercova.test'],
            [
                'name' => 'Administradora Mercova',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'phone' => '+573001234568',
                'bio' => 'Segunda administradora del marketplace.',
                'is_verified' => true,
                'is_active' => true,
            ]
        );

        $seller = User::updateOrCreate(
            ['email' => 'seller@mercova.test'],
            [
                'name' => 'Vendedor Mercova',
                'password' => Hash::make('password123'),
                'role' => 'seller',
                'phone' => '+573011112222',
                'bio' => 'Especialista en ventas y servicios.',
                'is_verified' => true,
            ]
        );

        $customer = User::updateOrCreate(
            ['email' => 'customer@mercova.test'],
            [
                'name' => 'Cliente Mercova',
                'password' => Hash::make('password123'),
                'role' => 'customer',
                'phone' => '+573022223333',
                'bio' => 'Comprador frecuente.',
                'is_verified' => true,
            ]
        );

        $tech = Category::where('slug', 'tecnologia')->first();
        $home = Category::where('slug', 'hogar')->first();
        $cars = Category::where('slug', 'vehiculos')->first();
        $moda = Category::where('slug', 'moda')->first();
        $deportes = Category::where('slug', 'deportes')->first();
        $services = Category::where('slug', 'servicios')->first();

        $products = [
            [
                'seller_id' => $seller->id,
                'category_id' => $tech->id,
                'title' => 'Laptop Gamer 15"',
                'description' => 'Laptop para trabajo y juegos con 16GB RAM y SSD de 512GB.',
                'price' => 3299900,
                'transaction_type' => 'sale',
                'stock' => 3,
                'condition' => 'Excelente',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $tech->id,
                'title' => 'Cámara Mirrorless 4K',
                'description' => 'Cámara profesional con lente 18-55mm, ideal para foto y video.',
                'price' => 180000,
                'transaction_type' => 'rent',
                'stock' => 1,
                'condition' => 'Excelente',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $tech->id,
                'title' => 'Audífonos Inalámbricos',
                'description' => 'Cancelación de ruido activa y 30 horas de batería.',
                'price' => 249900,
                'transaction_type' => 'sale',
                'stock' => 12,
                'condition' => 'Nuevo',
                'location' => 'Online',
                'image_url' => 'https://images.unsplash.com/photo-1583394838336-acd977736f90?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $home->id,
                'title' => 'Silla Ergonómica',
                'description' => 'Silla de oficina con respaldo lumbar y soporte ajustable.',
                'price' => 450000,
                'transaction_type' => 'sale',
                'stock' => 6,
                'condition' => 'Nueva',
                'location' => 'Medellín',
                'image_url' => 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $home->id,
                'title' => 'Sofá 3 Cuerpos',
                'description' => 'Sofá tapizado en lino gris, muy cómodo y resistente.',
                'price' => 1850000,
                'transaction_type' => 'sale',
                'stock' => 2,
                'condition' => 'Muy bueno',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $home->id,
                'title' => 'Set de Herramientas Eléctricas',
                'description' => 'Taladro, sierra y set de brocas para proyectos de bricolaje.',
                'price' => 60000,
                'transaction_type' => 'rent',
                'stock' => 4,
                'condition' => 'Buena',
                'location' => 'Cali',
                'image_url' => 'https://images.unsplash.com/photo-1504148455328-c376907d081c?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $cars->id,
                'title' => 'Bicicleta Eléctrica',
                'description' => 'Bicicleta de montaña con motor de asistencia y batería de 36V.',
                'price' => 120000,
                'transaction_type' => 'rent',
                'stock' => 2,
                'condition' => 'Muy buena',
                'location' => 'Cali',
                'image_url' => 'https://images.unsplash.com/photo-1558980664-10e7170b5df9?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $cars->id,
                'title' => 'Scooter Eléctrico Plegable',
                'description' => 'Ideal para moverse por la ciudad, autonomía de 25km.',
                'price' => 45000,
                'transaction_type' => 'rent',
                'stock' => 5,
                'condition' => 'Nueva',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1604868189265-219ba7bf7ea3?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $moda->id,
                'title' => 'Chaqueta de Cuero',
                'description' => 'Chaqueta de cuero genuino, corte clásico, talla M.',
                'price' => 320000,
                'transaction_type' => 'sale',
                'stock' => 4,
                'condition' => 'Nueva',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $moda->id,
                'title' => 'Vestido de Gala',
                'description' => 'Vestido largo elegante, perfecto para eventos especiales.',
                'price' => 90000,
                'transaction_type' => 'rent',
                'stock' => 3,
                'condition' => 'Excelente',
                'location' => 'Barranquilla',
                'image_url' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $deportes->id,
                'title' => 'Tabla de Surf 6\'2"',
                'description' => 'Tabla de surf para nivel intermedio, incluye funda.',
                'price' => 70000,
                'transaction_type' => 'rent',
                'stock' => 3,
                'condition' => 'Buena',
                'location' => 'Medellín',
                'image_url' => 'https://images.unsplash.com/photo-1502680390469-be75c86b636f?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $deportes->id,
                'title' => 'Set de Pesas Ajustables',
                'description' => 'Mancuernas ajustables de 2 a 20kg, ideales para entrenar en casa.',
                'price' => 380000,
                'transaction_type' => 'sale',
                'stock' => 5,
                'condition' => 'Nueva',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1517344884509-a0c97ec11bcc?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['slug' => Str::slug($product['title'])],
                [
                    ...$product,
                    'slug' => Str::slug($product['title']),
                    'price_per_day' => $product['transaction_type'] === 'rent' ? $product['price'] : null,
                    'commission_rate' => $commission->rate(),
                    'net_amount' => $commission->netFromGross((float) $product['price']),
                ]
            );
        }

        $serviceList = [
            [
                'seller_id' => $seller->id,
                'category_id' => $services->id,
                'title' => 'Diseño de Logo',
                'description' => 'Servicio de branding y diseño de logo para pequeñas empresas.',
                'price' => 250000,
                'duration' => '3 horas',
                'location' => 'Online',
                'image_url' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $services->id,
                'title' => 'Reparación de Electrodomésticos',
                'description' => 'Diagnóstico y mantenimiento técnico de equipos del hogar.',
                'price' => 90000,
                'duration' => '2 horas',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $services->id,
                'title' => 'Clases de Guitarra',
                'description' => 'Clases personalizadas para todos los niveles, presenciales u online.',
                'price' => 60000,
                'duration' => '1 hora',
                'location' => 'Online',
                'image_url' => 'https://images.unsplash.com/photo-1510915361894-db8b60106cb1?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $services->id,
                'title' => 'Paseo y Cuidado de Mascotas',
                'description' => 'Paseos diarios y cuidado a domicilio para perros y gatos.',
                'price' => 25000,
                'duration' => '45 minutos',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => true,
            ],
            [
                'seller_id' => $seller->id,
                'category_id' => $services->id,
                'title' => 'Servicio de Catering para Eventos',
                'description' => 'Menú personalizado para cumpleaños, matrimonios y reuniones corporativas.',
                'price' => 900000,
                'duration' => '4 horas',
                'location' => 'Bogotá',
                'image_url' => 'https://images.unsplash.com/photo-1555244162-803834f70033?auto=format&fit=crop&w=1200&q=80',
                'status' => 'active',
                'is_featured' => false,
            ],
        ];

        foreach ($serviceList as $service) {
            Service::updateOrCreate(
                ['slug' => Str::slug($service['title'])],
                [
                    ...$service,
                    'slug' => Str::slug($service['title']),
                    'commission_rate' => $commission->rate(),
                    'net_amount' => $commission->netFromGross((float) $service['price']),
                ]
            );
        }

        $proPlan = MembershipPlan::where('slug', 'pro')->first();

        $business = Business::updateOrCreate(
            ['user_id' => $seller->id],
            [
                'membership_plan_id' => $proPlan?->id,
                'name' => 'Taller Creativo Mercova',
                'slug' => 'taller-creativo-mercova',
                'description' => 'Emprendimiento local de diseño y manufactura artesanal.',
                'category' => 'Diseño y artesanía',
                'logo_url' => 'https://images.unsplash.com/photo-1607082349566-187342175e2f?auto=format&fit=crop&w=400&q=80',
                'phone' => '+573033334444',
                'address' => 'Bogotá, Colombia',
                'status' => 'active',
                'membership_started_at' => now(),
                'membership_expires_at' => now()->addMonth(),
            ]
        );

        Service::where('slug', 'diseno-de-logo')->update(['business_id' => $business->id]);

        $product = Product::where('slug', 'laptop-gamer-15')->first();
        $service = Service::where('slug', 'diseno-de-logo')->first();

        $orderBreakdown = $commission->breakdown((float) $product->price);

        $order = Order::firstOrCreate(
            ['buyer_id' => $customer->id, 'seller_id' => $seller->id, 'notes' => 'Pedido de prueba'],
            [
                'status' => 'pending',
                'total' => $product->price,
                'commission_amount' => $orderBreakdown['commission_amount'],
                'net_amount' => $orderBreakdown['net_amount'],
                'payment_method' => 'debit_card',
                'transaction_type' => $product->transaction_type,
                'recipient_name' => $customer->name,
                'recipient_phone' => $customer->phone,
                'address' => 'Calle 10 # 5-23',
                'city' => 'Bogotá',
                'department' => 'Cundinamarca',
            ]
        );

        if ($order->wasRecentlyCreated) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'type' => 'product',
                'quantity' => 1,
                'unit_price' => $product->price,
                'total' => $product->price,
                'commission_rate' => $orderBreakdown['commission_rate'],
                'commission_amount' => $orderBreakdown['commission_amount'],
                'net_amount' => $orderBreakdown['net_amount'],
            ]);
        }

        Booking::firstOrCreate(
            ['service_id' => $service->id, 'buyer_id' => $customer->id, 'notes' => 'Reserva inicial de demostración'],
            [
                'scheduled_at' => now()->addDays(2),
                'guest_count' => 1,
                'total' => $service->price,
                'commission_rate' => $commission->rate(),
                'commission_amount' => $commission->commissionAmount((float) $service->price),
                'net_amount' => $commission->netFromGross((float) $service->price),
                'status' => 'pending',
            ]
        );
    }
}
