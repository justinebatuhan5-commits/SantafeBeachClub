#!/usr/bin/env python3
"""Generate PWA icons for Santa Fe Beach Club"""

from PIL import Image
import os

# Create icons directory if it doesn't exist
os.makedirs('frontend/assets/icons', exist_ok=True)

# Load the original logo
logo = Image.open('frontend/assets/logo.jpg')

# Define colors - Beach Sunset theme (#ff6b6b)
beach_sunset_rgb = (255, 107, 107)  # #ff6b6b
white = (255, 255, 255)
transparent = (0, 0, 0, 0)

def create_icon(size, is_maskable=False):
    """Create a PWA icon at specified size"""
    # Resize logo to fit (leaving 10% margin)
    padding = int(size * 0.1)
    target_size = size - (padding * 2)
    
    # Resize original logo
    resized_logo = logo.resize((target_size, target_size), Image.Resampling.LANCZOS)
    
    # For maskable icons, create proper safe zone
    if is_maskable:
        # Create background with theme color
        bg = Image.new('RGB', (size, size), beach_sunset_rgb)
        # Convert resized logo to RGB and paste
        if resized_logo.mode == 'RGBA':
            # Create white version on colored background
            rgb_logo = Image.new('RGB', resized_logo.size, white)
            rgb_logo.paste(resized_logo, (0, 0), resized_logo)
            bg.paste(rgb_logo, (padding, padding))
        else:
            bg.paste(resized_logo, (padding, padding))
        return bg
    else:
        # Regular icon with white background
        bg = Image.new('RGB', (size, size), white)
        # Paste logo in center
        if resized_logo.mode == 'RGBA':
            # For transparency support, create RGBA
            bg_rgba = Image.new('RGBA', (size, size), (*white, 255))
            bg_rgba.paste(resized_logo, (padding, padding), resized_logo)
            return bg_rgba
        else:
            bg.paste(resized_logo, (padding, padding))
            return bg.convert('RGBA')

# Generate icons
print("Generating PWA icons...")

# Standard icons (white background)
icon_192 = create_icon(192, is_maskable=False)
icon_192.save('frontend/assets/icons/icon-192x192.png', 'PNG')
print("✓ Created icon-192x192.png")

icon_512 = create_icon(512, is_maskable=False)
icon_512.save('frontend/assets/icons/icon-512x512.png', 'PNG')
print("✓ Created icon-512x512.png")

# Maskable icons (with theme color background for adaptive icons)
maskable_192 = create_icon(192, is_maskable=True)
maskable_192.save('frontend/assets/icons/icon-maskable-192x192.png', 'PNG')
print("✓ Created icon-maskable-192x192.png")

maskable_512 = create_icon(512, is_maskable=True)
maskable_512.save('frontend/assets/icons/icon-maskable-512x512.png', 'PNG')
print("✓ Created icon-maskable-512x512.png")

print("\n✅ All PWA icons generated successfully!")
print("Location: frontend/assets/icons/")
print("\nIcon types:")
print("  • icon-192x192.png - Standard icon for app launcher")
print("  • icon-512x512.png - Large icon for splash screens")
print("  • icon-maskable-192x192.png - Adaptive icon (Android pie+)")
print("  • icon-maskable-512x512.png - Adaptive icon (Android pie+)")
