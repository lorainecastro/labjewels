<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" encoding="UTF-8" indent="yes"/>
    
    <xsl:template match="/store">
        <html lang="en">
            <head>
                <meta charset="UTF-8"/>
                <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
                <title><xsl:value-of select="metadata/name"/> - Product Catalog</title>
                <script src="https://cdn.tailwindcss.com"></script>
            </head>
            <body class="bg-gray-100 font-sans">
                <!-- Header Section -->
                <header class="bg-blue-600 text-white py-6 text-center">
                    <h1 class="text-4xl font-bold"><xsl:value-of select="metadata/name"/></h1>
                    <p class="mt-2 text-lg">Last Updated: <xsl:value-of select="metadata/last_updated"/></p>
                    <p>Currency: <xsl:value-of select="metadata/currency"/></p>
                    <p>Version: <xsl:value-of select="metadata/version"/></p>
                </header>

                <!-- Categories Section -->
                <section class="container mx-auto py-8">
                    <h2 class="text-2xl font-semibold mb-4">Categories</h2>
                    <div class="flex flex-wrap gap-4">
                        <xsl:for-each select="categories/category">
                            <span class="bg-blue-100 text-blue-800 px-4 py-2 rounded-full"><xsl:value-of select="."/></span>
                        </xsl:for-each>
                    </div>
                </section>

                <!-- Products Section -->
                <section class="container mx-auto py-8">
                    <h2 class="text-2xl font-semibold mb-6">Products</h2>
                    <xsl:for-each select="categories/category">
                        <xsl:variable name="currentCategory" select="."/>
                        <h3 class="text-xl font-medium mb-4"><xsl:value-of select="$currentCategory"/></h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                            <xsl:for-each select="/store/products/product[category=$currentCategory]">
                                <div class="bg-white p-6 rounded-lg shadow-md">
                                    <img src="{image}" alt="{name}" class="w-full h-48 object-cover rounded-md mb-4"/>
                                    <h4 class="text-lg font-semibold"><xsl:value-of select="name"/></h4>
                                    <p class="text-gray-600"><xsl:value-of select="description"/></p>
                                    <p class="mt-2"><strong>Material:</strong> <xsl:value-of select="material"/></p>
                                    <p><strong>Price:</strong> <xsl:value-of select="currency"/> <xsl:value-of select="format-number(price, '#,##0.00')"/></p>
                                    <p><strong>Stock:</strong> <xsl:value-of select="stock"/></p>
                                    <p><strong>Sizes:</strong> 
                                        <xsl:for-each select="sizes/size">
                                            <xsl:value-of select="."/><xsl:if test="position() != last()">, </xsl:if>
                                        </xsl:for-each>
                                    </p>
                                    <p><strong>Colors:</strong> 
                                        <xsl:for-each select="colors/color">
                                            <xsl:value-of select="."/><xsl:if test="position() != last()">, </xsl:if>
                                        </xsl:for-each>
                                    </p>
                                    <p><strong>Rating:</strong> <xsl:value-of select="rating"/> (<xsl:value-of select="review_count"/> reviews)</p>
                                    <xsl:if test="featured = 'true'">
                                        <p class="text-green-600 font-semibold">Featured Product</p>
                                    </xsl:if>
                                    <xsl:if test="on_sale = 'true'">
                                        <p class="text-red-600 font-semibold">On Sale!</p>
                                    </xsl:if>
                                </div>
                            </xsl:for-each>
                        </div>
                    </xsl:for-each>
                </section>

                <!-- Stock History Section -->
                <section class="container mx-auto py-8">
                    <h2 class="text-2xl font-semibold mb-4">Stock History</h2>
                    <table class="w-full border-collapse bg-white shadow-md rounded-lg">
                        <thead>
                            <tr class="bg-gray-200">
                                <th class="border p-3">Product ID</th>
                                <th class="border p-3">Product Name</th>
                                <th class="border p-3">Old Stock</th>
                                <th class="border p-3">New Stock</th>
                                <th class="border p-3">Change</th>
                                <th class="border p-3">Reason</th>
                                <th class="border p-3">Admin</th>
                                <th class="border p-3">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <xsl:for-each select="stock_history/entry">
                                <tr>
                                    <td class="border p-3"><xsl:value-of select="product_id"/></td>
                                    <td class="border p-3"><xsl:value-of select="product_name"/></td>
                                    <td class="border p-3"><xsl:value-of select="old_stock"/></td>
                                    <td class="border p-3"><xsl:value-of select="new_stock"/></td>
                                    <td class="border p-3"><xsl:value-of select="change"/></td>
                                    <td class="border p-3"><xsl:value-of select="reason"/></td>
                                    <td class="border p-3"><xsl:value-of select="admin_name"/> (ID: <xsl:value-of select="admin_id"/>)</td>
                                    <td class="border p-3"><xsl:value-of select="date"/></td>
                                </tr>
                            </xsl:for-each>
                        </tbody>
                    </table>
                </section>

                <!-- Footer -->
                <footer class="bg-gray-800 text-white py-6 text-center">
                    <p>2025 <xsl:value-of select="metadata/name"/>. All rights reserved.</p>
                </footer>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>