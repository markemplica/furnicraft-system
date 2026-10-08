<?php

namespace Database\Seeders;

use App\Models\FurnitureProduct;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerCatalogProductsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->orderBy('id')->first();
        $products = [
            ['Console', 'Wooden Door', 'Gmelina wood door, 90 × 210 cm, with a dark narra varnish finish.', 'Gmelina', 'Narra Look', 'Traditional', 18000, 90, 4, 210, 'catalog-console.png', ['door', 'console', 'dark narra']],
            ['3 PAD', 'Bed Frame', 'Mahogany wood double bed, 48 × 75 in (approximately 122 × 191 cm), with a natural mahogany finish.', 'Mahogany', 'Natural', 'Traditional', 8500, 122, 191, null, 'catalog-3-pad.png', ['bed', 'double', '3 pad', 'natural mahogany']],
            ['PIANO WITH S', 'Wooden Door', 'Gmelina wood door, 90 × 210 cm, with a narra look varnish finish.', 'Gmelina', 'Narra Look', 'Traditional', 7500, 90, 4, 210, 'catalog-piano-with-s.png', ['door', 'piano with s', 'narra look']],
            ['605', 'Wooden Door', 'Gmelina wood door, 80 × 210 cm, with a narra/mahogany varnish finish.', 'Gmelina', 'Mahogany', 'Traditional', 6000, 80, 4, 210, 'catalog-605.png', ['door', '605', 'narra mahogany']],
            ['channel', 'Wooden Door', 'Mahogany wood double door, 160 × 210 cm, with a natural mahogany finish.', 'Mahogany', 'Natural', 'Traditional', 20000, 160, 4, 210, 'catalog-channel.png', ['door', 'channel', 'double door', 'natural mahogany']],
            ['Special Design Piano with Diamond', 'Wooden Door', 'Gmelina wood door, 80 × 210 cm, with a Narra and Chocolate Brown varnish and decorative diamond details.', 'Gmelina', 'Narra / Chocolate Brown', 'Contemporary', 6500, 80, 4, 210, 'catalog-special-design-piano-diamond.png', ['door', 'piano', 'diamond', 'special design']],
            ['Engraved', 'Wooden Door', 'Mahogany wood door, 100 × 210 cm, with a Chocolate Brown and Brown Combo varnish and an engraved design.', 'Mahogany', 'Chocolate Brown / Brown Combo', 'Traditional', 20000, 100, 4, 210, 'catalog-engraved-door.png', ['door', 'engraved', 'mahogany']],
            ['7-Panel', 'Wooden Door', 'Gmelina wood 7-panel door, 90 × 210 cm, with a Narra Look varnish.', 'Gmelina', 'Narra Look', 'Traditional', 6500, 90, 4, 210, 'catalog-7-panel-door-cutout.png', ['door', '7 panel', 'narra look']],
            ['Bedframe Heart Curving', 'Bed Frame', 'Mangga wood queen bed frame for a 54 × 75 in mattress (approximately 137 × 191 cm), with a Mahogany Red finish. Overall frame measurements were not provided; confirm them with the shop.', 'Mangga', 'Mahogany Red', 'Traditional', 13500, null, null, null, 'catalog-bedframe-heart-curving.png', ['bed', 'queen', 'heart curving', 'mangga']],
            ['Bedframe Bricks', 'Bed Frame', 'Mangga wood queen bed frame with a brick-pattern panel design and Narra Look varnish. Sized for a 54 × 75 in queen mattress; overall frame measurements should be confirmed with the shop.', 'Mangga', 'Narra Look', 'Traditional', 13500, null, null, null, 'catalog-bedframe-bricks-cutout.png', ['bed', 'queen', 'bricks', 'brick pattern', 'mangga', '54x75']],
            ['Bedframe Flower Full Curving', 'Bed Frame', 'Mangga wood queen bed frame with a carved flower and full-curving headboard, finished in Narra Look varnish. Sized for a 54 × 75 in queen mattress; overall frame measurements should be confirmed with the shop.', 'Mangga', 'Narra Look', 'Traditional', 13500, null, null, null, 'catalog-bedframe-flower-full-curving-cutout.png', ['bed', 'queen', 'flower', 'full curving', 'mangga', '54x75']],
            ['Chinese Double Deck w/ Drawer', 'Double-Deck Bed', 'Gmelina wood double-deck bed with drawer and Narra Look varnish. Mattress options: Queen 54 × 75 in or Single 36 × 75 in. Confirm overall frame measurements with the shop.', 'Gmelina', 'Narra Look', 'Practical', 19000, null, null, null, 'catalog-chinese-double-deck-drawer-cutout.png', ['bed', 'double deck', 'bunk bed', 'drawer', 'queen', 'single', '54x75', '36x75']],
            ['Taiwan Double Deck w/ 3 Drawer', 'Double-Deck Bed', 'Gmelina wood double-deck bed with three drawers and Light Narra Look varnish. Mattress options: Double 48 × 75 in or Single 36 × 75 in. Confirm overall frame measurements with the shop.', 'Gmelina', 'Light Narra Look', 'Practical', 18500, null, null, null, 'catalog-taiwan-double-deck-3-drawer-cutout.png', ['bed', 'double deck', 'bunk bed', 'three drawer', 'double', 'single', '48x75', '36x75']],
            ['Indian Door', 'Wooden Door', 'Mahogany wood door, 90 × 210 cm, with a mahogany varnish finish and geometric Indian-inspired detailing.', 'Mahogany', 'Mahogany', 'Traditional', 40000, 90, 4, 210, 'catalog-indian-door.png', ['door', 'indian door', 'mahogany', 'geometric']],
            ['Piano w/ S and Diamond', 'Wooden Door', 'Gmelina wood door, 90 × 210 cm, with a Narra Look and Black varnish finish, decorative diamond accents, and an S design.', 'Gmelina', 'Narra Look / Black', 'Contemporary', 9500, 90, 4, 210, 'catalog-piano-s-diamond.png', ['door', 'piano', 's design', 'diamond', 'black']],
            ['Piano w/ Lining Black', 'Wooden Door', 'Gmelina wood door, 90 × 210 cm, with a Narra Look and Black varnish finish and black lining.', 'Gmelina', 'Narra Look / Black', 'Contemporary', 6500, 90, 4, 210, 'catalog-piano-lining-black.png', ['door', 'piano', 'black lining', 'black']],
        ];
        foreach ($products as [$name, $category, $description, $wood, $finish, $style, $price, $width, $depth, $height, $image, $tags]) {
            FurnitureProduct::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'category' => $category, 'description' => $description, 'wood_type' => $wood, 'varnish_finish' => $finish, 'style' => $style, 'price' => $price, 'width_cm' => $width, 'depth_cm' => $depth, 'height_cm' => $height, 'image_path' => 'catalog:'.$image, 'tags' => $tags, 'status' => 'active', 'created_by' => $admin?->id]);
        }
    }
}
