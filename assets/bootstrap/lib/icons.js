import { createElement, ArrowLeft, Check, ChevronDown, ChevronRight, CircleAlert, CircleCheck, CircleX, Clock, Droplet, FileText, Globe, House, Info, LayoutDashboard, LoaderCircle, Lock, LogOut, Mail, Menu, Minus, Package, PackageOpen, Pencil, Phone, Plus, Receipt, RefreshCw, Search, Settings, ShoppingCart, Store, Tag, Trash2, TriangleAlert, Truck, User, Users, WifiOff, X } from 'lucide';
import iconMap from '../../shared/icons.json';

/** Lucide icon nodes by Lucide name. Every icon in assets/shared/icons.json must be here (npm run check:icons). */
const ICONS = {
    'arrow-left': ArrowLeft,
    'check': Check,
    'chevron-down': ChevronDown,
    'chevron-right': ChevronRight,
    'circle-alert': CircleAlert,
    'circle-check': CircleCheck,
    'circle-x': CircleX,
    'clock': Clock,
    'droplet': Droplet,
    'file-text': FileText,
    'globe': Globe,
    'house': House,
    'info': Info,
    'layout-dashboard': LayoutDashboard,
    'loader-circle': LoaderCircle,
    'lock': Lock,
    'log-out': LogOut,
    'mail': Mail,
    'minus': Minus,
    'menu': Menu,
    'package': Package,
    'package-open': PackageOpen,
    'pencil': Pencil,
    'phone': Phone,
    'plus': Plus,
    'receipt': Receipt,
    'refresh-cw': RefreshCw,
    'search': Search,
    'settings': Settings,
    'shopping-cart': ShoppingCart,
    'store': Store,
    'tag': Tag,
    'trash-2': Trash2,
    'triangle-alert': TriangleAlert,
    'truck': Truck,
    'user': User,
    'users': Users,
    'wifi-off': WifiOff,
    'x': X,
};

/**
 * An <svg> for a semantic icon name from assets/shared/icons.json, e.g. icon('success').
 *
 * @param {string} name
 * @param {string} [className]
 * @returns {SVGElement}
 */
export function icon(name, className = '') {
    const node = ICONS[iconMap[name]];
    if (!node) {
        throw new Error(`Unknown icon "${name}". Add it to assets/shared/icons.json and assets/bootstrap/lib/icons.js.`);
    }
    const svg = createElement(node);
    svg.setAttribute('class', `icon ${className}`.trim());
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('width', '1em');
    svg.setAttribute('height', '1em');
    return svg;
}
